(function () {
  var wrap = document.querySelector('.bootg-calc-wrap');
  if (!wrap) { return; }

  var indexView = wrap.querySelector('.bootg-calc-view-index');
  var singleWraps = wrap.querySelectorAll('.bootg-calc-single-wrap');
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
      var unitsCeil = Math.ceil(units);
      return {
        units: isFinite(units) ? fmtNum(unitsCeil) + ' units' : 'Not achievable at this price',
        revenue: isFinite(units) ? fmtMoney(units * f.price_per_unit) : '—',
        summary: isFinite(units)
          ? 'Based on these numbers, you’d need to sell about ' + fmtNum(unitsCeil) + ' units (' + fmtMoney(units * f.price_per_unit) + ' in revenue) to cover your costs.'
          : 'Your variable cost is the same as or higher than your selling price, so break-even isn’t achievable at this price.'
      };
    },
    'burn-rate': function (f) {
      var netBurn = f.monthly_expenses - f.monthly_revenue;
      var runway = netBurn > 0 ? f.cash_balance / netBurn : NaN;
      return {
        net_burn: fmtMoney(netBurn) + ' / month',
        runway: netBurn <= 0 ? 'Cash flow positive' : fmtNum(runway, 1) + ' months',
        summary: netBurn <= 0
          ? 'You’re cash flow positive at these numbers — revenue covers expenses, so your runway isn’t limited by burn.'
          : 'At this rate, your cash reserves will last about ' + fmtNum(runway, 1) + ' months before running out.'
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
        total_repayment: fmtMoney(total),
        summary: 'A ' + fmtMoney(f.loan_amount) + ' loan over ' + fmtNum(n) + ' months works out to about ' + fmtMoney(payment) + ' a month, ' + fmtMoney(total - f.loan_amount) + ' in total interest.'
      };
    },
    'business-value': function (f) {
      return {
        value_low: fmtMoney(f.annual_profit * Math.max(0, f.multiple - 0.5)),
        value_mid: fmtMoney(f.annual_profit * f.multiple),
        value_high: fmtMoney(f.annual_profit * (f.multiple + 0.5)),
        summary: 'At a ' + fmtNum(f.multiple, 1) + 'x multiple, this business is roughly worth ' + fmtMoney(f.annual_profit * f.multiple) + '.'
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
        reading: isFinite(ratio) ? reading : '—',
        summary: isFinite(ratio)
          ? 'Your current ratio is ' + fmtNum(ratio, 2) + ' — ' + reading.charAt(0).toLowerCase() + reading.slice(1) + '.'
          : 'Enter your current liabilities to see a result.'
      };
    },
    'discount': function (f) {
      var discounted = f.original_price * (1 - f.discount_percent / 100);
      var marginBefore = f.original_price > 0 ? ((f.original_price - f.cost_per_unit) / f.original_price) * 100 : NaN;
      var marginAfter = discounted > 0 ? ((discounted - f.cost_per_unit) / discounted) * 100 : NaN;
      return {
        discounted_price: fmtMoney(discounted),
        margin_before: fmtPercent(marginBefore),
        margin_after: fmtPercent(marginAfter),
        summary: 'A ' + fmtNum(f.discount_percent, 0) + '% discount drops the price to ' + fmtMoney(discounted) + ', taking your margin from ' + fmtPercent(marginBefore) + ' to ' + fmtPercent(marginAfter) + '.'
      };
    },
    'equipment-loan-lease': function (f) {
      var r = (f.loan_rate / 100) / 12;
      var n = f.loan_term_months;
      var loanPayment = r === 0 ? f.equipment_cost / n : f.equipment_cost * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
      var loanTotal = loanPayment * n;
      var leaseTotal = f.lease_monthly_payment * f.lease_term_months;
      var verdict = loanTotal === leaseTotal ? 'Same total cost' : (loanTotal < leaseTotal ? 'Loan is cheaper overall' : 'Lease is cheaper overall');
      return {
        loan_total: fmtMoney(loanTotal),
        lease_total: fmtMoney(leaseTotal),
        verdict: verdict,
        summary: verdict + ' — the loan totals ' + fmtMoney(loanTotal) + ' versus ' + fmtMoney(leaseTotal) + ' for the lease over their terms.'
      };
    },
    'gross-profit-break-even': function (f) {
      var revenue = f.gross_margin_percent > 0 ? f.fixed_costs / (f.gross_margin_percent / 100) : NaN;
      return {
        revenue: isFinite(revenue) ? fmtMoney(revenue) : '—',
        summary: isFinite(revenue)
          ? 'You need about ' + fmtMoney(revenue) + ' in revenue to cover your fixed costs at a ' + fmtNum(f.gross_margin_percent, 0) + '% gross margin.'
          : 'Enter a gross margin above 0% to see a result.'
      };
    },
    'product-pricing': function (f) {
      var price = f.desired_margin_percent < 100 ? f.cost_per_unit / (1 - f.desired_margin_percent / 100) : NaN;
      var markup = f.cost_per_unit > 0 && isFinite(price) ? ((price - f.cost_per_unit) / f.cost_per_unit) * 100 : NaN;
      return {
        selling_price: isFinite(price) ? fmtMoney(price) : '—',
        markup_percent: isFinite(markup) ? fmtPercent(markup) : '—',
        summary: isFinite(price)
          ? 'To hit a ' + fmtNum(f.desired_margin_percent, 0) + '% margin on a ' + fmtMoney(f.cost_per_unit) + ' cost, price this at ' + fmtMoney(price) + '.'
          : 'Keep the desired margin under 100% to see a result.'
      };
    },
    'profit-improvement': function (f) {
      var currentProfit = f.current_revenue - f.current_costs;
      var newRevenue = f.current_revenue * (1 + f.price_increase_percent / 100) * (1 + f.volume_increase_percent / 100);
      var newCosts = f.current_costs * (1 - f.cost_reduction_percent / 100) * (1 + f.volume_increase_percent / 100);
      var newProfit = newRevenue - newCosts;
      var change = newProfit - currentProfit;
      return {
        current_profit: fmtMoney(currentProfit),
        new_profit: fmtMoney(newProfit),
        profit_change: fmtMoney(change) + (currentProfit !== 0 ? ' (' + fmtPercent((change / Math.abs(currentProfit)) * 100) + ')' : ''),
        summary: 'Those changes together move profit from ' + fmtMoney(currentProfit) + ' to ' + fmtMoney(newProfit) + ' — a ' + (change >= 0 ? 'gain' : 'drop') + ' of ' + fmtMoney(Math.abs(change)) + '.'
      };
    },
    'start-up-costs': function (f) {
      var total = f.equipment + f.inventory + f.licensing + f.marketing + f.working_capital + f.other;
      return {
        total: fmtMoney(total),
        summary: 'Launching with these figures will cost roughly ' + fmtMoney(total) + ' in total.'
      };
    },
    'gst': function (f) {
      var gst, result;
      if (f.direction === 'remove') {
        gst = f.amount - (f.amount / 1.1);
        result = f.amount - gst;
        return {
          gst_amount: fmtMoney(gst),
          result: fmtMoney(result),
          summary: fmtMoney(f.amount) + ' includes ' + fmtMoney(gst) + ' GST, leaving ' + fmtMoney(result) + ' excluding GST.'
        };
      }
      gst = f.amount * 0.1;
      result = f.amount + gst;
      return {
        gst_amount: fmtMoney(gst),
        result: fmtMoney(result),
        summary: fmtMoney(f.amount) + ' plus 10% GST (' + fmtMoney(gst) + ') comes to ' + fmtMoney(result) + '.'
      };
    },
    'credit-card-repayment': function (f) {
      var balance = f.balance;
      var monthlyRate = (f.apr / 100) / 12;
      var payment = f.monthly_payment;
      var months = 0;
      var totalPaid = 0;
      if (payment <= balance * monthlyRate) {
        return {
          months: 'Never — payment too low to cover interest',
          total_interest: '—',
          total_paid: '—',
          summary: 'At ' + fmtMoney(payment) + ' a month, your payment doesn’t even cover the interest being charged — the balance will keep growing. Increase the monthly payment to pay it off.'
        };
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
        total_paid: fmtMoney(totalPaid),
        summary: 'Paying ' + fmtMoney(payment) + ' a month clears this balance in ' + fmtNum(months) + ' months, costing ' + fmtMoney(totalPaid - f.balance) + ' in interest.'
      };
    },
    'retirement-savings': function (f) {
      var r = (f.annual_return_percent / 100) / 12;
      var n = f.years * 12;
      var fv = r === 0
        ? f.current_savings + f.monthly_contribution * n
        : f.current_savings * Math.pow(1 + r, n) + f.monthly_contribution * ((Math.pow(1 + r, n) - 1) / r);
      return {
        future_value: fmtMoney(fv),
        summary: 'At this contribution rate and return, you’re projected to have about ' + fmtMoney(fv) + ' after ' + fmtNum(f.years) + ' years.'
      };
    },
    'retirement-drawdown': function (f) {
      var balance = f.savings_balance;
      var rate = f.annual_return_percent / 100;
      var withdrawal = f.annual_withdrawal;
      if (withdrawal <= balance * rate) {
        return {
          years: 'Indefinitely — withdrawals stay under investment growth',
          summary: 'Your annual withdrawal is lower than what this balance earns in growth each year, so it won’t run out at this rate.'
        };
      }
      var years = 0;
      while (balance > 0 && years < 100) {
        balance = balance * (1 + rate) - withdrawal;
        years++;
      }
      return {
        years: fmtNum(years) + ' years',
        summary: 'Withdrawing ' + fmtMoney(withdrawal) + ' a year, this balance is projected to last about ' + fmtNum(years) + ' years.'
      };
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
        total_interest: fmtMoney(fv - contributions),
        summary: 'After ' + fmtNum(f.years) + ' years you’re projected to have ' + fmtMoney(fv) + ', including ' + fmtMoney(fv - contributions) + ' in interest earned.'
      };
    }
  };

  function recalc(calcId) {
    var fn = CALCULATORS[calcId];
    if (!fn) { return; }
    var values = fn(getFields(calcId));
    setOutputs(calcId, values);
  }

  /* Wire each calculator panel: number<->slider sync, recalc on input, tabs. */
  panels.forEach(function (panel) {
    var calcId = panel.getAttribute('data-calc');

    panel.querySelectorAll('[data-field]').forEach(function (el) {
      el.addEventListener('input', function () {
        var key = el.getAttribute('data-field');
        var slider = panel.querySelector('.bootg-calc-slider[data-slider-for="' + key + '"]');
        if (slider && el.tagName !== 'SELECT') {
          var min = parseFloat(slider.min), max = parseFloat(slider.max);
          var v = parseFloat(el.value);
          if (isFinite(v)) { slider.value = Math.min(Math.max(v, min), max); }
        }
        recalc(calcId);
      });
      el.addEventListener('change', function () { recalc(calcId); });
    });

    panel.querySelectorAll('.bootg-calc-slider[data-slider-for]').forEach(function (slider) {
      slider.addEventListener('input', function () {
        var key = slider.getAttribute('data-slider-for');
        var field = panel.querySelector('[data-field="' + key + '"]');
        if (field) { field.value = slider.value; }
        recalc(calcId);
      });
    });

    recalc(calcId);

    var backBtn = panel.querySelector('[data-back]');
    if (backBtn) {
      backBtn.addEventListener('click', function () {
        var singleWrap = panel.closest('.bootg-calc-single-wrap');
        if (singleWrap) { singleWrap.hidden = true; }
        if (indexView) { indexView.hidden = false; }
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    var tabButtons = panel.querySelectorAll('.bootg-calc-tab');
    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-tab');
        tabButtons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        panel.querySelectorAll('.bootg-calc-tab-panel').forEach(function (p) {
          p.hidden = (p.getAttribute('data-tab-panel') !== target);
        });
      });
    });
  });

  /* Index "Open" buttons only exist in the all-in-one shortcode view (the
     standalone archive page links to real URLs instead). */
  wrap.querySelectorAll('[data-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-open');
      var singleWrap = wrap.querySelector('.bootg-calc-view-single[data-calc="' + target + '"]');
      if (singleWrap) { singleWrap = singleWrap.closest('.bootg-calc-single-wrap'); }
      if (!singleWrap) { return; }
      if (indexView) { indexView.hidden = true; }
      singleWraps.forEach(function (w) { w.hidden = (w !== singleWrap); });
      recalc(target);
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
