(function () {
  var wrap = document.querySelector('.bootg-calc-wrap');
  if (!wrap) { return; }

  var indexView = wrap.querySelector('.bootg-calc-view-index');
  var panels = wrap.querySelectorAll('.bootg-calc-view-single');

  function fmtMoney(n) {
    if (!isFinite(n)) { return '—'; }
    var sign = n < 0 ? '-' : '';
    return sign + '$' + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function fmtNum(n, decimals) {
    if (!isFinite(n)) { return '—'; }
    return n.toLocaleString(undefined, { minimumFractionDigits: decimals || 0, maximumFractionDigits: decimals || 2 });
  }
  function fmtPercent(n) {
    if (!isFinite(n)) { return '—'; }
    return fmtNum(n, 1) + '%';
  }

  function getFields(calcId) {
    var out = {};
    wrap.querySelectorAll('[data-field][data-calc="' + calcId + '"]').forEach(function (el) {
      var key = el.getAttribute('data-field');
      out[key] = el.tagName === 'SELECT' ? el.value : parseFloat(el.value);
    });
    return out;
  }

  function setOutputs(calcId, values) {
    var resultsEl = wrap.querySelector('.bootg-calc-results[data-calc-results="' + calcId + '"]');
    if (!resultsEl) { return; }
    Object.keys(values).forEach(function (key) {
      var el = resultsEl.querySelector('[data-output="' + key + '"]');
      if (el) { el.textContent = values[key]; }
    });
  }

  var CALCULATORS = {
    'break-even': function (f) {
      var margin = f.price_per_unit - f.variable_cost;
      var units = margin > 0 ? f.fixed_costs / margin : NaN;
      return {
        units: isFinite(units) ? fmtNum(Math.ceil(units)) + ' units' : 'Not achievable at this price',
        revenue: isFinite(units) ? fmtMoney(units * f.price_per_unit) : '—'
      };
    },
    'burn-rate': function (f) {
      var netBurn = f.monthly_expenses - f.monthly_revenue;
      var runway = netBurn > 0 ? f.cash_balance / netBurn : NaN;
      return {
        net_burn: fmtMoney(netBurn) + ' / month',
        runway: netBurn <= 0 ? 'Cash flow positive' : fmtNum(runway, 1) + ' months'
      };
    },
    'business-loan': function (f) {
      var r = (f.interest_rate / 100) / 12;
      var n = f.term_months;
      var payment = r === 0 ? f.loan_amount / n : f.loan_amount * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
      var total = payment * n;
      return {
        monthly_payment: fmtMoney(payment),
        total_interest: fmtMoney(total - f.loan_amount),
        total_repayment: fmtMoney(total)
      };
    },
    'business-value': function (f) {
      return {
        value_low: fmtMoney(f.annual_profit * Math.max(0, f.multiple - 0.5)),
        value_mid: fmtMoney(f.annual_profit * f.multiple),
        value_high: fmtMoney(f.annual_profit * (f.multiple + 0.5))
      };
    },
    'current-ratio': function (f) {
      var ratio = f.current_liabilities > 0 ? f.current_assets / f.current_liabilities : NaN;
      var reading = 'At risk — liabilities exceed assets';
      if (ratio >= 2) { reading = 'Strong — healthy buffer'; }
      else if (ratio >= 1.5) { reading = 'Good'; }
      else if (ratio >= 1) { reading = 'OK, but tight'; }
      return {
        ratio: isFinite(ratio) ? fmtNum(ratio, 2) : '—',
        reading: isFinite(ratio) ? reading : '—'
      };
    },
    'discount': function (f) {
      var discounted = f.original_price * (1 - f.discount_percent / 100);
      var marginBefore = f.original_price > 0 ? ((f.original_price - f.cost_per_unit) / f.original_price) * 100 : NaN;
      var marginAfter = discounted > 0 ? ((discounted - f.cost_per_unit) / discounted) * 100 : NaN;
      return {
        discounted_price: fmtMoney(discounted),
        margin_before: fmtPercent(marginBefore),
        margin_after: fmtPercent(marginAfter)
      };
    },
    'equipment-loan-lease': function (f) {
      var r = (f.loan_rate / 100) / 12;
      var n = f.loan_term_months;
      var loanPayment = r === 0 ? f.equipment_cost / n : f.equipment_cost * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
      var loanTotal = loanPayment * n;
      var leaseTotal = f.lease_monthly_payment * f.lease_term_months;
      return {
        loan_total: fmtMoney(loanTotal),
        lease_total: fmtMoney(leaseTotal),
        verdict: loanTotal === leaseTotal ? 'Same total cost' : (loanTotal < leaseTotal ? 'Loan is cheaper overall' : 'Lease is cheaper overall')
      };
    },
    'gross-profit-break-even': function (f) {
      var revenue = f.gross_margin_percent > 0 ? f.fixed_costs / (f.gross_margin_percent / 100) : NaN;
      return { revenue: isFinite(revenue) ? fmtMoney(revenue) : '—' };
    },
    'product-pricing': function (f) {
      var price = f.desired_margin_percent < 100 ? f.cost_per_unit / (1 - f.desired_margin_percent / 100) : NaN;
      var markup = f.cost_per_unit > 0 && isFinite(price) ? ((price - f.cost_per_unit) / f.cost_per_unit) * 100 : NaN;
      return {
        selling_price: isFinite(price) ? fmtMoney(price) : '—',
        markup_percent: isFinite(markup) ? fmtPercent(markup) : '—'
      };
    },
    'profit-improvement': function (f) {
      var currentProfit = f.current_revenue - f.current_costs;
      var newRevenue = f.current_revenue * (1 + f.price_increase_percent / 100) * (1 + f.volume_increase_percent / 100);
      var newCosts = f.current_costs * (1 - f.cost_reduction_percent / 100) * (1 + f.volume_increase_percent / 100);
      var newProfit = newRevenue - newCosts;
      return {
        current_profit: fmtMoney(currentProfit),
        new_profit: fmtMoney(newProfit),
        profit_change: fmtMoney(newProfit - currentProfit) + (currentProfit !== 0 ? ' (' + fmtPercent(((newProfit - currentProfit) / Math.abs(currentProfit)) * 100) + ')' : '')
      };
    },
    'start-up-costs': function (f) {
      var total = f.equipment + f.inventory + f.licensing + f.marketing + f.working_capital + f.other;
      return { total: fmtMoney(total) };
    },
    'gst': function (f) {
      var gst, result;
      if (f.direction === 'remove') {
        gst = f.amount - (f.amount / 1.1);
        result = f.amount - gst;
      } else {
        gst = f.amount * 0.1;
        result = f.amount + gst;
      }
      return {
        gst_amount: fmtMoney(gst),
        result: fmtMoney(result)
      };
    },
    'credit-card-repayment': function (f) {
      var balance = f.balance;
      var monthlyRate = (f.apr / 100) / 12;
      var payment = f.monthly_payment;
      var months = 0;
      var totalPaid = 0;
      if (payment <= balance * monthlyRate) {
        return { months: 'Never — payment too low to cover interest', total_interest: '—', total_paid: '—' };
      }
      while (balance > 0 && months < 600) {
        var interest = balance * monthlyRate;
        var principal = Math.min(payment - interest, balance);
        balance -= principal;
        totalPaid += principal + interest;
        months++;
      }
      return {
        months: fmtNum(months) + ' months',
        total_interest: fmtMoney(totalPaid - f.balance),
        total_paid: fmtMoney(totalPaid)
      };
    },
    'retirement-savings': function (f) {
      var r = (f.annual_return_percent / 100) / 12;
      var n = f.years * 12;
      var fv = r === 0
        ? f.current_savings + f.monthly_contribution * n
        : f.current_savings * Math.pow(1 + r, n) + f.monthly_contribution * ((Math.pow(1 + r, n) - 1) / r);
      return { future_value: fmtMoney(fv) };
    },
    'retirement-drawdown': function (f) {
      var balance = f.savings_balance;
      var rate = f.annual_return_percent / 100;
      var withdrawal = f.annual_withdrawal;
      if (withdrawal <= balance * rate) {
        return { years: 'Indefinitely — withdrawals stay under investment growth' };
      }
      var years = 0;
      while (balance > 0 && years < 100) {
        balance = balance * (1 + rate) - withdrawal;
        years++;
      }
      return { years: fmtNum(years) + ' years' };
    },
    'savings-future-value': function (f) {
      var r = (f.annual_rate_percent / 100) / 12;
      var n = f.years * 12;
      var fv = r === 0
        ? f.initial_deposit + f.monthly_deposit * n
        : f.initial_deposit * Math.pow(1 + r, n) + f.monthly_deposit * ((Math.pow(1 + r, n) - 1) / r);
      var contributions = f.initial_deposit + f.monthly_deposit * n;
      return {
        future_value: fmtMoney(fv),
        total_contributions: fmtMoney(contributions),
        total_interest: fmtMoney(fv - contributions)
      };
    }
  };

  function recalc(calcId) {
    var fn = CALCULATORS[calcId];
    if (!fn) { return; }
    var values = fn(getFields(calcId));
    setOutputs(calcId, values);
  }

  panels.forEach(function (panel) {
    var calcId = panel.getAttribute('data-calc');
    panel.querySelectorAll('[data-field]').forEach(function (el) {
      el.addEventListener('input', function () { recalc(calcId); });
      el.addEventListener('change', function () { recalc(calcId); });
    });
    recalc(calcId);

    var backBtn = panel.querySelector('[data-back]');
    if (backBtn) {
      backBtn.addEventListener('click', function () {
        panel.hidden = true;
        indexView.hidden = false;
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
  });

  wrap.querySelectorAll('[data-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-open');
      var panel = wrap.querySelector('.bootg-calc-view-single[data-calc="' + target + '"]');
      if (!panel) { return; }
      indexView.hidden = true;
      panels.forEach(function (p) { p.hidden = (p !== panel); });
      recalc(target);
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
