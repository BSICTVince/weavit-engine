<?php
/**
 * Business + personal calculators, built in-house rather than an embedded
 * third-party iframe. All math runs client-side (assets/js/calculators.js);
 * this file owns the calculator catalogue (titles, descriptions, about/help
 * copy, input fields, output rows) plus two ways to reach it:
 *
 *  - [bootg_calculators_widget] shortcode: every calculator on one page,
 *    switched client-side (no page reload) — good for embedding anywhere.
 *  - A `bootg_calculator` custom post type gives each calculator its own
 *    real, unique, bookmarkable URL (e.g. /calculators/gst/) via
 *    [bootg_calculators_index]: a self-created "Calculators" Page is the
 *    index, and each calculator post's content is injected via
 *    `the_content`. Both the Page and the posts are created automatically
 *    on init, so activating this plugin on any WordPress site reproduces
 *    the whole thing with no theme changes required — it just inherits
 *    whatever page template the site already uses for its other pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( ! bootg_module_enabled( 'calculators' ) ) {
		return;
	}
	add_shortcode( 'bootg_calculators_widget', 'bootg_render_calculators_shortcode' );
	add_shortcode( 'bootg_calculators_index', 'bootg_render_calculators_index_shortcode' );
	bootg_register_calculator_cpt();
	bootg_calculators_sync_posts();
}, 5 );

/**
 * Catalogue of every calculator: id, category, title, description, about/
 * help copy, the input fields (key, label, unit prefix/suffix, default,
 * step), and the output rows (key, label) that calculators.js fills in.
 * Field/output keys are what the JS reads/writes via data-field /
 * data-output, so they must match the case IDs in calculators.js exactly.
 */
function bootg_calculators_definitions() {
	return array(
		'break-even'              => array(
			'category'    => 'business',
			'title'       => 'Break-even',
			'description' => 'Work out how many units you need to sell, or how much revenue you need, to cover your fixed and variable costs.',
			'about'       => <<<'HTML'
<p>This calculator shows you how many units you need to sell — or how much money you need to bring in — before you start making a profit. Before that point you're just covering your costs; after it, every extra sale is profit.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Is my price high enough to make this worth doing?</li>
<li>If my rent or costs go up, how many more sales do I need to stay afloat?</li>
<li>Is the sales target I've set actually realistic?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Fixed costs</strong> — the bills that stay the same no matter how much you sell, like rent, wages, insurance and software. (Example: $10,000.)</li>
<li><strong>Selling price per unit</strong> — what you charge for one item or service. (Example: $50.)</li>
<li><strong>Variable cost per unit</strong> — what it costs you to make or deliver one more item, like materials or packaging. (Example: $20.)</li>
</ul>
<p><strong>How it works</strong><br>We take your fixed costs and divide them by how much profit you make on each unit (price minus variable cost). That tells you how many units it takes to cover everything.</p>
<p><strong>What the result tells you</strong><br>With the example numbers above, you'd need to sell about 334 units (around $16,700 in sales) before you start making a profit. Sell more than that and the extra is yours to keep.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to use this calculator to make better decisions:</p>
<ol>
<li><strong>Try different prices.</strong> Raise the selling price slightly and watch the number of units you need to sell drop. <em>Example:</em> going from $50 to $55 per unit means fewer sales needed to break even.</li>
<li><strong>Cut your fixed costs.</strong> A smaller office, working from home, or outsourcing admin work can all lower your fixed costs. <em>Example:</em> dropping rent from $2,000 to $1,500 a month saves $6,000 a year, lowering your break-even point.</li>
<li><strong>Shop around on materials.</strong> A cheaper supplier or a bulk discount lowers your variable cost per unit, which means more profit on every sale. <em>Example:</em> saving 50 cents per unit on 1,000 units is $500 back in your pocket.</li>
<li><strong>Check your sales target is realistic.</strong> If the calculator says you need to sell 1,000 units a month, do you actually have the customers and capacity for that?</li>
</ol>
<p><strong>In short:</strong> this isn't just a number — it's a quick way to test "what if" scenarios before you commit to a price, a new cost, or a sales target.</p>
HTML,
			'fields'      => array(
				array( 'fixed_costs', 'Fixed costs', '$', '', 10000, 1 ),
				array( 'price_per_unit', 'Selling price per unit', '$', '', 50, 1 ),
				array( 'variable_cost', 'Variable cost per unit', '$', '', 20, 1 ),
			),
			'outputs'     => array(
				array( 'units', 'Break-even units' ),
				array( 'revenue', 'Break-even revenue' ),
			),
		),
		'burn-rate'               => array(
			'category'    => 'business',
			'title'       => 'Burn rate',
			'description' => 'Monitor your cash burn and see how many months your current cash reserves will last at your current spending rate.',
			'about'       => <<<'HTML'
<p>"Burn rate" just means how fast your business is spending cash. If you're spending more than you're bringing in, your cash balance shrinks a little every month — this calculator tells you how many months you have left before it runs out, so you can act early instead of being caught out.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>How much time do I have before I need to find more income or cut costs?</li>
<li>Is now a safe time to invest in new equipment or staff?</li>
<li>If I don't change anything, when do I run into trouble?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Current cash balance</strong> — the money actually sitting in your bank account right now. (Example: $50,000.)</li>
<li><strong>Monthly expenses</strong> — everything leaving the business each month: wages, rent, subscriptions, stock. (Example: $12,000.)</li>
<li><strong>Monthly revenue</strong> — what's actually coming in from sales each month. (Example: $4,000.)</li>
</ul>
<p><strong>How it works</strong><br>We subtract your monthly revenue from your monthly expenses to find your "net burn" — how much cash you lose each month — then divide your cash balance by that number.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, you're losing $8,000 a month, which gives you about 6 months of cash left. If revenue is higher than expenses, you're not burning cash at all — your runway is effectively unlimited.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to use this calculator to buy yourself more time:</p>
<ol>
<li><strong>Chase overdue invoices.</strong> Money owed to you doesn't help your cash balance until it's actually paid. <em>Example:</em> collecting $5,000 in overdue invoices adds $5,000 straight to your runway.</li>
<li><strong>Trim non-essential spending.</strong> Look at subscriptions, tools, or services you're not fully using. <em>Example:</em> cancelling $300 a month in unused software adds a month of runway over a year.</li>
<li><strong>Bring revenue forward.</strong> A promotion, a follow-up call to warm leads, or asking for deposits upfront can speed up money coming in.</li>
<li><strong>Know your trigger point.</strong> Decide in advance what you'll do if your runway drops below, say, 3 months — raise prices, cut costs, or look for extra funding — so you're not deciding under pressure.</li>
</ol>
<p><strong>In short:</strong> this number is your early warning system. The earlier you know your runway, the more options you have.</p>
HTML,
			'fields'      => array(
				array( 'cash_balance', 'Current cash balance', '$', '', 50000, 1 ),
				array( 'monthly_expenses', 'Monthly expenses', '$', '', 12000, 1 ),
				array( 'monthly_revenue', 'Monthly revenue', '$', '', 4000, 1 ),
			),
			'outputs'     => array(
				array( 'net_burn', 'Net monthly burn' ),
				array( 'runway', 'Runway (months)' ),
			),
		),
		'business-loan'           => array(
			'category'    => 'business',
			'title'       => 'Business loan',
			'description' => 'Calculate the monthly repayment, total interest, and total repayment for a business loan.',
			'about'       => <<<'HTML'
<p>This calculator works out what your monthly repayment would be on a business loan, and how much of what you pay back ends up being interest rather than the amount you actually borrowed.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Can I actually afford the monthly repayment on this loan?</li>
<li>How much extra am I really paying because of interest?</li>
<li>Would a shorter or longer loan term work out better for me?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Loan amount</strong> — how much you're borrowing. (Example: $50,000.)</li>
<li><strong>Annual interest rate</strong> — the yearly rate your lender quotes you (not the monthly rate — we work that out for you). (Example: 7.5%.)</li>
<li><strong>Loan term</strong> — how long you have to pay it back, in months. (Example: 36 months, i.e. 3 years.)</li>
</ul>
<p><strong>How it works</strong><br>We spread the loan amount plus interest evenly across every monthly repayment, the same way a bank would calculate it, so each payment is the same amount from the first month to the last.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, you'd repay about $1,555 a month, paying around $5,991 in interest over the life of the loan — a total repayment of about $55,991 for a $50,000 loan.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth checking before you sign for a loan:</p>
<ol>
<li><strong>Compare the total repayment, not just the monthly figure.</strong> A longer term often means a smaller monthly payment but more interest overall. <em>Example:</em> stretching a loan from 36 to 60 months lowers the monthly cost but usually increases the total interest paid.</li>
<li><strong>Check for extra fees.</strong> Establishment fees, account-keeping fees, or early repayment penalties aren't included here — ask your lender for the full picture.</li>
<li><strong>Make sure the repayment fits your cash flow.</strong> Use this alongside the Burn rate calculator to see if the monthly repayment is something your business can comfortably absorb.</li>
<li><strong>Shop around.</strong> Even a 1% difference in interest rate can add up to real money over a few years — it's worth comparing more than one lender.</li>
</ol>
<p><strong>In short:</strong> knowing your real monthly repayment and total interest upfront helps you borrow with your eyes open, rather than being surprised later.</p>
HTML,
			'fields'      => array(
				array( 'loan_amount', 'Loan amount', '$', '', 50000, 1 ),
				array( 'interest_rate', 'Annual interest rate', '', '%', 7.5, 0.1 ),
				array( 'term_months', 'Loan term', '', 'months', 36, 1 ),
			),
			'outputs'     => array(
				array( 'monthly_payment', 'Monthly payment' ),
				array( 'total_interest', 'Total interest' ),
				array( 'total_repayment', 'Total repayment' ),
			),
		),
		'business-value'          => array(
			'category'    => 'business',
			'title'       => 'Business value',
			'description' => 'Get a rough valuation estimate of your business based on annual profit and a chosen earnings multiple.',
			'about'       => <<<'HTML'
<p>This calculator gives you a rough, back-of-envelope idea of what your business might be worth, based on how much profit it makes each year. It's a quick starting point for a conversation — not a formal valuation.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Roughly what could I expect if I sold the business one day?</li>
<li>Is the business becoming more valuable as profit grows?</li>
<li>What would a buyer or investor likely offer, in broad terms?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Annual revenue</strong> — your total sales for the year, before costs. (Example: $300,000.)</li>
<li><strong>Annual profit</strong> — what's left after all business costs, including a fair wage for yourself if you work in the business. (Example: $60,000.)</li>
<li><strong>Industry multiple</strong> — a number that reflects what similar businesses in your industry typically sell for, as a multiple of profit. Ask your bookkeeper or accountant what's typical for your industry — 2 to 4 times profit is common for small service businesses. (Example: 3x.)</li>
</ul>
<p><strong>How it works</strong><br>We simply multiply your annual profit by the multiple you enter, and also show a slightly lower and slightly higher estimate either side of it, since real-world offers usually land in a range rather than one exact number.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, a $60,000 profit at a 3x multiple gives a rough value of around $180,000 (roughly $150,000 to $210,000 either side).</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to use this calculator to think about growing the value of your business:</p>
<ol>
<li><strong>Focus on profit, not just revenue.</strong> A business with lower revenue but better profit is often worth more than one with high revenue and thin margins. <em>Example:</em> improving your profit margin by a few percent can lift your estimated value more than chasing extra sales at the same margin.</li>
<li><strong>Keep clean, up-to-date books.</strong> A buyer (or a bank, if you're borrowing against the business) will trust a number backed by proper bookkeeping far more than an estimate from memory.</li>
<li><strong>Reduce reliance on you personally.</strong> A business that can run without the owner being hands-on every day is usually seen as less risky, and less risky often means a higher multiple.</li>
<li><strong>Revisit this yearly.</strong> Tracking this estimate over time shows whether the changes you're making are actually building value, not just revenue.</li>
</ol>
<p><strong>In short:</strong> this is a conversation-starter, not a contract price — use it to track direction, and get a professional valuation when a real decision is on the table.</p>
HTML,
			'fields'      => array(
				array( 'annual_revenue', 'Annual revenue', '$', '', 300000, 1 ),
				array( 'annual_profit', 'Annual profit (SDE)', '$', '', 60000, 1 ),
				array( 'multiple', 'Industry multiple', '', 'x', 3, 0.1 ),
			),
			'outputs'     => array(
				array( 'value_low', 'Estimated value (low)' ),
				array( 'value_mid', 'Estimated value' ),
				array( 'value_high', 'Estimated value (high)' ),
			),
		),
		'current-ratio'           => array(
			'category'    => 'business',
			'title'       => 'Current ratio',
			'description' => 'Check your short-term financial health: do you have enough current assets to cover current liabilities?',
			'about'       => <<<'HTML'
<p>This is a quick health check: can you cover what you owe in the next year with what you can turn into cash in the next year? It's one of the first things a bank or accountant looks at to judge how financially stable a business is.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Could I cover my bills if things got tight for a few months?</li>
<li>Am I relying too much on short-term debt?</li>
<li>Is my financial position improving or getting worse over time?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Current assets</strong> — cash, money owed to you by customers, and any stock you'd sell within the next year. (Example: $80,000.)</li>
<li><strong>Current liabilities</strong> — what you owe within the next year: supplier bills, short-term loans, upcoming tax. (Example: $40,000.)</li>
</ul>
<p>Both figures should be on your most recent balance sheet — your bookkeeper can point you to them if you're not sure.</p>
<p><strong>How it works</strong><br>We simply divide your current assets by your current liabilities.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, your ratio is 2.0, which is considered strong — you have twice as much coming in as you owe in the short term. A ratio below 1 means your short-term bills are bigger than what you can quickly turn into cash, which is worth addressing.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to improve this number if it's lower than you'd like:</p>
<ol>
<li><strong>Chase up what's owed to you.</strong> Unpaid invoices count as an asset on paper, but only help your cash position once they're actually paid. <em>Example:</em> following up on $5,000 of overdue invoices can measurably improve your ratio once collected.</li>
<li><strong>Review slow-moving stock.</strong> Stock that isn't selling ties up money that could be cash instead — consider a sale or discount to clear it.</li>
<li><strong>Negotiate payment terms.</strong> Longer terms with suppliers, or asking customers to pay faster, both help your short-term position.</li>
<li><strong>Keep an eye on short-term debt.</strong> Relying heavily on short-term loans or a maxed-out line of credit pulls this ratio down even if the business is otherwise healthy.</li>
</ol>
<p><strong>In short:</strong> this ratio is a simple early-warning check — worth glancing at every few months, not just at tax time.</p>
HTML,
			'fields'      => array(
				array( 'current_assets', 'Current assets', '$', '', 80000, 1 ),
				array( 'current_liabilities', 'Current liabilities', '$', '', 40000, 1 ),
			),
			'outputs'     => array(
				array( 'ratio', 'Current ratio' ),
				array( 'reading', 'What this means' ),
			),
		),
		'discount'                => array(
			'category'    => 'business',
			'title'       => 'Discount',
			'description' => 'See how a product discount affects your selling price and profit margin.',
			'about'       => <<<'HTML'
<p>A discount feels good for customers, but it eats straight into your profit margin. This calculator shows you exactly what a discount does to your price and your margin, so you can offer one with your eyes open instead of guessing.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Can I actually afford to run this sale or promotion?</li>
<li>How much of my profit am I giving away with this discount?</li>
<li>What's the biggest discount I can offer before I'm barely making money?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Original price</strong> — what you'd normally charge. (Example: $100.)</li>
<li><strong>Discount</strong> — the percentage off you're considering. (Example: 20%.)</li>
<li><strong>Cost per unit</strong> — what the item actually costs you to make or buy (not what you sell it for). (Example: $40.)</li>
</ul>
<p><strong>How it works</strong><br>We apply the discount to your price, then work out your profit margin — what percentage of the sale price is actually profit — both before and after the discount, so you can compare them side by side.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, a 20% discount drops your $100 price to $80. Your margin falls from 60% down to 50% — still healthy, but worth knowing before you commit.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth checking before you run a discount:</p>
<ol>
<li><strong>Set a floor you won't go below.</strong> Decide in advance the lowest margin you're willing to accept, so a "quick sale" doesn't accidentally become a loss.</li>
<li><strong>Think about volume.</strong> A smaller margin can still work if the discount brings in enough extra sales to make up for it — just make sure that extra volume is realistic, not hopeful.</li>
<li><strong>Compare against your cost, not just your usual price.</strong> As long as the discounted price is above your cost per unit, you're still making some profit — below it, you're paying customers to take stock off your hands.</li>
<li><strong>Use discounts with a purpose.</strong> Clearing old stock, winning a new customer, or filling a quiet period are all good reasons for a discount — discounting out of habit usually just trains customers to wait for the next sale.</li>
</ol>
<p><strong>In short:</strong> a discount can be a smart move or a quiet profit leak — this calculator helps you tell the difference before you offer it.</p>
HTML,
			'fields'      => array(
				array( 'original_price', 'Original price', '$', '', 100, 1 ),
				array( 'discount_percent', 'Discount', '', '%', 20, 1 ),
				array( 'cost_per_unit', 'Cost per unit', '$', '', 40, 1 ),
			),
			'outputs'     => array(
				array( 'discounted_price', 'Discounted price' ),
				array( 'margin_before', 'Margin before discount' ),
				array( 'margin_after', 'Margin after discount' ),
			),
		),
		'equipment-loan-lease'    => array(
			'category'    => 'business',
			'title'       => 'Equipment loan vs lease',
			'description' => 'Compare the total lifetime cost of financing equipment with a loan versus leasing it.',
			'about'       => <<<'HTML'
<p>Buying equipment with a loan and leasing it both get you the same equipment, but they can cost quite different amounts once you add everything up. This calculator compares the total you'd actually pay under each option, so you can see which works out cheaper.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Is it cheaper overall to buy this equipment or lease it?</li>
<li>How much extra am I really paying by leasing instead of buying?</li>
<li>Which option fits my monthly cash flow better?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Equipment cost, loan rate and loan term</strong> — if you bought it outright with a loan. We work out the loan repayments for you. (Example: $40,000 at 8% over 48 months.)</li>
<li><strong>Lease monthly payment and lease term</strong> — taken straight from your lease quote. (Example: $950 a month for 48 months.)</li>
</ul>
<p><strong>How it works</strong><br>For the loan, we calculate the monthly repayment and add it up over the full term. For the lease, we simply multiply the monthly payment by the number of months. Then we compare the two totals.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, the loan totals around $46,870 while the lease totals $45,600 — in this case, leasing comes out about $1,270 cheaper over the four years. Change the numbers to match your actual quotes to see which wins for you.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth thinking about beyond just the total cost:</p>
<ol>
<li><strong>Ownership matters too.</strong> At the end of a loan, you own the equipment outright — at the end of a lease, you often don't (unless there's a buy-out option). Factor that into your comparison.</li>
<li><strong>Check what's included in the lease.</strong> Some leases bundle in maintenance or replacement, which a loan wouldn't cover — that can make a slightly more expensive lease worth it.</li>
<li><strong>Think about how long you'll actually use it.</strong> If the equipment might be outdated or unnecessary in a couple of years, leasing can avoid being stuck with something you no longer need.</li>
<li><strong>Compare the monthly cash flow impact, not just the total.</strong> A loan with a bigger deposit might have a lower monthly cost than a lease — run both through the Business loan calculator to check.</li>
</ol>
<p><strong>In short:</strong> the cheapest option on paper isn't always the best fit for your business — use this as one input alongside how you actually plan to use the equipment.</p>
HTML,
			'fields'      => array(
				array( 'equipment_cost', 'Equipment cost', '$', '', 40000, 1 ),
				array( 'loan_rate', 'Loan annual interest rate', '', '%', 8, 0.1 ),
				array( 'loan_term_months', 'Loan term', '', 'months', 48, 1 ),
				array( 'lease_monthly_payment', 'Lease monthly payment', '$', '', 950, 1 ),
				array( 'lease_term_months', 'Lease term', '', 'months', 48, 1 ),
			),
			'outputs'     => array(
				array( 'loan_total', 'Total loan cost' ),
				array( 'lease_total', 'Total lease cost' ),
				array( 'verdict', 'Cheaper option' ),
			),
		),
		'gross-profit-break-even' => array(
			'category'    => 'business',
			'title'       => 'Gross profit break-even',
			'description' => 'Calculate the revenue needed to reach break-even based on your gross margin.',
			'about'       => <<<'HTML'
<p>This is a faster version of the Break-even calculator for when you already know your overall profit margin percentage, rather than working it out per product. It's especially handy for service businesses or businesses selling lots of different products at different prices.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>How much do I need to sell in total to cover my overheads?</li>
<li>If my margin improves, how much less do I need to sell to break even?</li>
<li>Is my current sales level actually covering my costs?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Fixed costs</strong> — the bills that stay the same regardless of sales, like rent, wages and insurance. (Example: $15,000.)</li>
<li><strong>Gross margin</strong> — the percentage of each sales dollar that's left after covering the direct cost of what you sold. You'll usually find this on a recent profit and loss report, or your bookkeeper can confirm it for you. (Example: 40%.)</li>
</ul>
<p><strong>How it works</strong><br>We divide your fixed costs by your gross margin percentage to find the total sales figure needed to cover those costs.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, you'd need about $37,500 in sales to cover $15,000 of fixed costs at a 40% margin. Below that level, your costs aren't fully covered yet.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to bring this break-even point down:</p>
<ol>
<li><strong>Improve your margin.</strong> Even a small increase in your gross margin lowers the sales you need to cover costs. <em>Example:</em> moving from a 40% to a 45% margin reduces the revenue needed to break even without selling a single extra unit.</li>
<li><strong>Review your fixed costs regularly.</strong> Subscriptions, rent, and service contracts tend to creep up over time — an annual review can catch savings you'd otherwise miss.</li>
<li><strong>Know your number before busy season.</strong> If you know you need $37,500 in sales to break even, you can track progress toward that each month rather than finding out at tax time.</li>
<li><strong>Use it alongside your pricing decisions.</strong> Check the Product pricing calculator to see how adjusting prices changes your margin — and therefore this break-even figure.</li>
</ol>
<p><strong>In short:</strong> this gives you one clear target number to aim for each month, instead of guessing whether you're on track.</p>
HTML,
			'fields'      => array(
				array( 'fixed_costs', 'Fixed costs', '$', '', 15000, 1 ),
				array( 'gross_margin_percent', 'Gross margin', '', '%', 40, 1 ),
			),
			'outputs'     => array(
				array( 'revenue', 'Break-even revenue' ),
			),
		),
		'product-pricing'         => array(
			'category'    => 'business',
			'title'       => 'Product pricing',
			'description' => 'Set a selling price from your unit cost and desired profit margin.',
			'about'       => <<<'HTML'
<p>Rather than picking a price because it "sounds about right," this calculator works backwards from the profit margin you actually want, so your price is built on a number you chose on purpose.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>What should I actually charge to hit my target profit margin?</li>
<li>Am I currently underpricing what I sell?</li>
<li>How much room do I have to discount before my margin gets too thin?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Cost per unit</strong> — what it actually costs you to make or buy one item. (Example: $25.)</li>
<li><strong>Desired margin</strong> — the percentage of the final selling price you want left as profit, after covering that cost. Keep this under 100%. (Example: 35%.)</li>
</ul>
<p><strong>How it works</strong><br>We work backwards from your cost and your target margin to find the selling price that leaves you with exactly that margin, rather than just adding a flat markup on top of cost.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, a $25 cost at a 35% margin means pricing at about $38.46 — a markup of roughly 54% on cost.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth knowing about pricing from margin:</p>
<ol>
<li><strong>Margin and markup aren't the same thing.</strong> A 35% margin is not the same as a 35% markup on cost — this calculator shows you both, so you're never caught out by the difference.</li>
<li><strong>Check your price against the market.</strong> If the price that gives you your target margin is well above what competitors charge, you may need to either accept a lower margin or find a way to justify the higher price (quality, service, speed).</li>
<li><strong>Revisit pricing when costs change.</strong> If your supplier costs go up, your price needs to move too if you want to protect the same margin — re-run this calculator any time your cost per unit changes.</li>
<li><strong>Different products can have different targets.</strong> Not every product needs the same margin — you might accept a lower margin on a popular item that brings in other sales.</li>
</ol>
<p><strong>In short:</strong> pricing from your target margin, rather than guessing, protects your profit as your costs change over time.</p>
HTML,
			'fields'      => array(
				array( 'cost_per_unit', 'Cost per unit', '$', '', 25, 1 ),
				array( 'desired_margin_percent', 'Desired margin', '', '%', 35, 1 ),
			),
			'outputs'     => array(
				array( 'selling_price', 'Selling price' ),
				array( 'markup_percent', 'Markup on cost' ),
			),
		),
		'profit-improvement'      => array(
			'category'    => 'business',
			'title'       => 'Profit improvement',
			'description' => 'Model how small changes to price, cost and volume affect your bottom line.',
			'about'       => <<<'HTML'
<p>Small changes add up more than most people expect. This calculator lets you test a modest price rise, a cost saving, and a bit more sales volume all at once, to see how much they move your bottom line together.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Which change would actually make the biggest difference — price, costs, or volume?</li>
<li>If I raise prices slightly, how much extra profit does that actually create?</li>
<li>Do I need a big change, or would a few small ones get me where I want to be?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Current annual revenue and costs</strong> — your business's current numbers for the year. (Example: $200,000 revenue, $160,000 costs.)</li>
<li><strong>Price increase, cost reduction and volume increase</strong> — the percentage change you're considering for each. Set any of these to 0% to test the others on their own. (Example: 5% price increase, 3% cost reduction, 2% more volume.)</li>
</ul>
<p><strong>How it works</strong><br>We apply all three changes to your current revenue and costs at once, then compare your current profit to the projected profit after the changes.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, current profit is $40,000. The combined changes lift projected profit to roughly $55,900 — a gain of about $15,900, or close to 40% more profit, from fairly modest individual changes.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to use this to find your best next move:</p>
<ol>
<li><strong>Test one lever at a time first.</strong> Set cost reduction and volume increase to 0% and only change the price, to see exactly how much a price rise alone is worth. Repeat for each lever separately.</li>
<li><strong>Small price increases are often the easiest win.</strong> A 5% price rise usually has less impact on whether customers stay than people expect, and it goes straight to profit.</li>
<li><strong>Look for savings that don't affect quality.</strong> Switching suppliers, renegotiating contracts, or cutting waste are lower-risk ways to trim costs than cutting corners customers would notice.</li>
<li><strong>Volume growth isn't free.</strong> More sales often means more marketing spend or more of your time — make sure the volume increase you're testing is realistic for what it would actually take to achieve.</li>
</ol>
<p><strong>In short:</strong> you don't need one big dramatic change — a handful of small, realistic improvements together can meaningfully lift your profit.</p>
HTML,
			'fields'      => array(
				array( 'current_revenue', 'Current annual revenue', '$', '', 200000, 1 ),
				array( 'current_costs', 'Current annual costs', '$', '', 160000, 1 ),
				array( 'price_increase_percent', 'Price increase', '', '%', 5, 0.5 ),
				array( 'cost_reduction_percent', 'Cost reduction', '', '%', 3, 0.5 ),
				array( 'volume_increase_percent', 'Volume increase', '', '%', 2, 0.5 ),
			),
			'outputs'     => array(
				array( 'current_profit', 'Current profit' ),
				array( 'new_profit', 'Projected profit' ),
				array( 'profit_change', 'Profit change' ),
			),
		),
		'start-up-costs'          => array(
			'category'    => 'business',
			'title'       => 'Start-up costs',
			'description' => 'Add up everything you need to launch: equipment, inventory, licensing, marketing and working capital.',
			'about'       => <<<'HTML'
<p>Most new businesses underestimate what it actually costs to open the doors. This calculator adds up the common categories in one place, so you have a realistic total to plan or budget against before you start spending.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>How much money do I actually need to get started?</li>
<li>Have I forgotten to budget for something important?</li>
<li>Do I need to raise or save more before I launch?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Equipment</strong> — tools, machinery, computers, fit-out. (Example: $5,000.)</li>
<li><strong>Inventory</strong> — stock you need on hand before you can start selling. (Example: $8,000.)</li>
<li><strong>Licensing & registration</strong> — business registration, licences, insurance set-up. (Example: $1,500.)</li>
<li><strong>Marketing & branding</strong> — logo, website, launch promotion. (Example: $3,000.)</li>
<li><strong>Working capital</strong> — cash set aside to cover day-to-day costs like rent, wages and stock while the business is still building up its own income. Most advisors suggest budgeting at least 3-6 months of running costs here. (Example: $10,000.)</li>
<li><strong>Other costs</strong> — anything else specific to your business. (Example: $2,000.)</li>
</ul>
<p><strong>How it works</strong><br>We simply add up every category you enter into one total.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, launching would cost around $29,500 in total — a realistic figure to plan, save, or raise funding against, rather than being caught short partway through setting up.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth doing before you finalise this number:</p>
<ol>
<li><strong>Get real quotes, not guesses.</strong> Actual quotes for equipment, fit-out or licensing are usually higher than a rough guess — building this in early avoids a nasty surprise later.</li>
<li><strong>Don't skimp on working capital.</strong> Most new businesses take longer than expected to become profitable — underestimating working capital is one of the most common reasons new businesses run into trouble early.</li>
<li><strong>Add a buffer.</strong> Consider adding 10-15% on top of your total for the unexpected costs that always seem to come up.</li>
<li><strong>Revisit it as plans firm up.</strong> Update each category as you get real quotes, so your funding or savings target stays accurate right up to launch.</li>
</ol>
<p><strong>In short:</strong> knowing this number early means fewer surprises and a much smoother launch.</p>
HTML,
			'fields'      => array(
				array( 'equipment', 'Equipment', '$', '', 5000, 1 ),
				array( 'inventory', 'Inventory', '$', '', 8000, 1 ),
				array( 'licensing', 'Licensing & registration', '$', '', 1500, 1 ),
				array( 'marketing', 'Marketing & branding', '$', '', 3000, 1 ),
				array( 'working_capital', 'Working capital', '$', '', 10000, 1 ),
				array( 'other', 'Other costs', '$', '', 2000, 1 ),
			),
			'outputs'     => array(
				array( 'total', 'Total start-up cost' ),
			),
		),
		'gst'                     => array(
			'category'    => 'business',
			'title'       => 'GST calculator',
			'description' => 'Quickly add or remove 10% GST from an Australian price.',
			'about'       => <<<'HTML'
<p>Australia's GST rate is a flat 10%. This calculator either adds GST onto a price that doesn't have it yet, or works out how much GST is hiding inside a price that already includes it.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>What should I actually charge a customer, GST included?</li>
<li>How much GST have I collected on a sale?</li>
<li>What's the real pre-tax amount on this receipt or invoice?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Amount</strong> — the dollar figure you're starting with. (Example: $100.)</li>
<li><strong>I want to</strong> — choose "Add GST" if your amount doesn't include GST yet (like a quoted cost price), or "Remove GST" if it already includes GST (like a receipt or invoice total) and you want to see the GST portion and the amount before tax.</li>
</ul>
<p><strong>How it works</strong><br>Adding GST means multiplying your amount by 10% and adding that on. Removing GST means working out what 10% of the final price actually was, which isn't quite the same as just taking 10% off — we handle that calculation for you either way.</p>
<p><strong>What the result tells you</strong><br>With the example amount of $100 and "Add GST" selected, the GST component is $10 and the total to charge is $110.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth knowing when working with GST:</p>
<ol>
<li><strong>Always check which way you're going.</strong> Mixing up "add" and "remove" is one of the most common invoicing mistakes — this calculator labels both clearly so you can double-check before sending an invoice.</li>
<li><strong>Quoted prices should say whether GST is included.</strong> Being clear with customers upfront (e.g. "$110 including GST") avoids awkward conversations later.</li>
<li><strong>Keep this handy at tax time.</strong> Quickly checking GST on individual transactions can help spot errors before they end up on your BAS.</li>
<li><strong>When in doubt, ask your bookkeeper.</strong> This calculator handles the maths, but GST rules can get more complex for certain transactions, exports, or GST-free items.</li>
</ol>
<p><strong>In short:</strong> a quick, reliable way to check GST on the fly, without reaching for a spreadsheet.</p>
HTML,
			'fields'      => array(
				array( 'amount', 'Amount', '$', '', 100, 1 ),
				array(
					'direction',
					'I want to',
					'',
					'',
					'add',
					0,
					'select',
					array(
						'add'    => 'Add GST (price excludes GST)',
						'remove' => 'Remove GST (price includes GST)',
					),
				),
			),
			'outputs'     => array(
				array( 'gst_amount', 'GST amount (10%)' ),
				array( 'result', 'Result' ),
			),
		),
		'credit-card-repayment'   => array(
			'category'    => 'personal',
			'title'       => 'Credit card repayment',
			'description' => 'Find out how long it will take to pay off your credit card at a fixed monthly payment, and how much interest you will pay.',
			'about'       => <<<'HTML'
<p>Credit card interest is charged every month, so paying only a little above the minimum can take far longer — and cost far more — than most people expect. This calculator shows you the real timeline and the real cost of paying it off at a fixed monthly payment.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>How long will it actually take me to clear this balance?</li>
<li>How much am I really paying in interest?</li>
<li>If I increase my monthly payment, how much time and money would I save?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Current balance</strong> — what you currently owe on the card. (Example: $5,000.)</li>
<li><strong>Annual interest rate (APR)</strong> — the yearly interest rate printed on your card statement. (Example: 19.9%.)</li>
<li><strong>Monthly payment</strong> — how much you plan to pay each month. (Example: $200.)</li>
</ul>
<p><strong>How it works</strong><br>Each month, interest is added to what you still owe, then your payment reduces the balance. We repeat this month by month until the balance reaches zero, adding up the interest along the way.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, paying $200 a month clears a $5,000 balance in about 33 months (just under 3 years), and you'll pay roughly $1,511 in interest — a total of about $6,511 paid for a $5,000 balance.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to pay this off faster and cheaper:</p>
<ol>
<li><strong>Pay more than the minimum whenever you can.</strong> Even a small increase in your monthly payment can cut months — and real dollars — off the total. <em>Example:</em> try changing the monthly payment in this calculator from $200 to $250 and see the difference.</li>
<li><strong>Stop adding new spending to the card.</strong> New purchases on top of the balance you're paying down undo your progress and extend the timeline.</li>
<li><strong>Check if a balance transfer makes sense.</strong> Some cards offer a lower or 0% rate for a limited time on transferred balances — just be clear on what the rate becomes afterward.</li>
<li><strong>If the payment doesn't even cover the interest, act now.</strong> This calculator will warn you if that's the case — the balance will keep growing instead of shrinking until the payment increases.</li>
</ol>
<p><strong>In short:</strong> seeing the real timeline and real cost upfront makes it much easier to commit to a payment plan that actually works.</p>
HTML,
			'fields'      => array(
				array( 'balance', 'Current balance', '$', '', 5000, 1 ),
				array( 'apr', 'Annual interest rate (APR)', '', '%', 19.9, 0.1 ),
				array( 'monthly_payment', 'Monthly payment', '$', '', 200, 1 ),
			),
			'outputs'     => array(
				array( 'months', 'Months to pay off' ),
				array( 'total_interest', 'Total interest' ),
				array( 'total_paid', 'Total paid' ),
			),
		),
		'retirement-savings'      => array(
			'category'    => 'personal',
			'title'       => 'Retirement savings',
			'description' => 'Project how your retirement savings will grow with regular contributions and compound interest.',
			'about'       => <<<'HTML'
<p>Small, regular contributions can grow into a surprisingly large balance over a long enough time, thanks to compound interest — your returns start earning their own returns. This calculator projects where your current savings, plus what you keep contributing, could land by the time you retire.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Am I on track for the retirement balance I want?</li>
<li>How much difference would contributing a bit more each month really make?</li>
<li>What happens to my projected balance if I retire a few years earlier or later?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Current savings</strong> — what you've already got saved towards retirement. (Example: $20,000.)</li>
<li><strong>Monthly contribution</strong> — how much you add each month. (Example: $500.)</li>
<li><strong>Years until retirement</strong> — how long you plan to keep contributing. (Example: 25 years.)</li>
<li><strong>Expected annual return</strong> — depends on how the savings are invested; more conservative options tend to average lower returns, growth-focused options higher but with more ups and downs year to year. If you're unsure, 6-8% is a commonly used long-term assumption, not a guarantee. (Example: 7%.)</li>
</ul>
<p><strong>How it works</strong><br>We grow your current savings and every monthly contribution forward using your expected return, compounding month by month over the years you enter.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, $20,000 today plus $500 a month for 25 years at a 7% return projects to roughly $519,500 by the time you retire.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth exploring with this calculator:</p>
<ol>
<li><strong>Test a small increase in your contribution.</strong> Because of compounding, even an extra $50-$100 a month can make a meaningfully bigger difference the earlier you start it.</li>
<li><strong>See what starting earlier is worth.</strong> Try adding a few extra years to see how much more time in the market changes the projected balance — it's often more powerful than people expect.</li>
<li><strong>Be realistic about returns.</strong> A higher assumed return looks better on paper, but it should reflect how your savings are actually invested, not just a hopeful number.</li>
<li><strong>Revisit this yearly.</strong> Update your current savings and contribution amount each year to keep the projection accurate as your situation changes.</li>
</ol>
<p><strong>In short:</strong> this isn't a guarantee — it's a projection to help you judge whether your current plan is roughly on track, or needs adjusting.</p>
HTML,
			'fields'      => array(
				array( 'current_savings', 'Current savings', '$', '', 20000, 1 ),
				array( 'monthly_contribution', 'Monthly contribution', '$', '', 500, 1 ),
				array( 'years', 'Years until retirement', '', 'years', 25, 1 ),
				array( 'annual_return_percent', 'Expected annual return', '', '%', 7, 0.1 ),
			),
			'outputs'     => array(
				array( 'future_value', 'Projected balance at retirement' ),
			),
		),
		'retirement-drawdown'     => array(
			'category'    => 'personal',
			'title'       => 'Retirement drawdown',
			'description' => 'See how many years your retirement savings will last at a given annual withdrawal rate.',
			'about'       => <<<'HTML'
<p>Once you stop adding to your savings and start withdrawing from them instead, the question flips: how long will the balance actually last? This calculator models your savings going up or down each year as withdrawals come out and investment growth goes in.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Will my savings actually last as long as I need them to?</li>
<li>How much could I safely withdraw each year without running out too soon?</li>
<li>What happens if I withdraw a bit more, or a bit less, each year?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Savings balance</strong> — your total savings at the point you start withdrawing. (Example: $600,000.)</li>
<li><strong>Annual withdrawal</strong> — how much you plan to take out each year. (Example: $40,000.)</li>
<li><strong>Expected annual return</strong> — the yearly growth rate you expect on the remaining balance while it's still invested. (Example: 5%.)</li>
</ul>
<p><strong>How it works</strong><br>Each year, we grow the balance by your expected return, then subtract your annual withdrawal — repeating that year by year until the balance reaches zero.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, $600,000 withdrawing $40,000 a year at a 5% return is projected to last about 29 years. If your withdrawal is lower than what the balance earns each year, the savings technically never run out — the calculator will tell you when that's the case.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few things worth testing with this calculator:</p>
<ol>
<li><strong>Try a slightly lower withdrawal.</strong> Dropping your annual withdrawal even a little can add years onto how long the balance lasts — see how sensitive the result is in your situation.</li>
<li><strong>Don't assume a fixed return every year.</strong> Real investment returns go up and down year to year — this calculator uses a steady average to keep things simple, so treat the result as a guide, not a guarantee.</li>
<li><strong>Factor in other income.</strong> If you'll have other income during retirement (like a pension), you may not need to withdraw as much from these savings, which changes the picture.</li>
<li><strong>Review the numbers regularly.</strong> Update the balance and withdrawal amount each year so the projection stays realistic as your situation and the markets change.</li>
</ol>
<p><strong>In short:</strong> this gives you an early read on whether your withdrawal plan is sustainable, so you can adjust before it becomes a problem rather than after.</p>
HTML,
			'fields'      => array(
				array( 'savings_balance', 'Savings balance', '$', '', 600000, 1 ),
				array( 'annual_withdrawal', 'Annual withdrawal', '$', '', 40000, 1 ),
				array( 'annual_return_percent', 'Expected annual return', '', '%', 5, 0.1 ),
			),
			'outputs'     => array(
				array( 'years', 'Years the savings will last' ),
			),
		),
		'savings-future-value'    => array(
			'category'    => 'personal',
			'title'       => 'Savings future value',
			'description' => 'Model how regular savings deposits grow over time with compound interest.',
			'about'       => <<<'HTML'
<p>A general-purpose savings projection — not specific to retirement — for any goal where you're putting away a lump sum plus regular monthly amounts and want to know roughly what it grows into by a target date.</p>
<p>It can help you answer questions like:</p>
<ul>
<li>Will I reach my savings goal in time if I keep going at this rate?</li>
<li>How much of the final amount is actually my own money versus interest earned?</li>
<li>What happens if I save a bit more each month?</li>
</ul>
<p><strong>What you'll need to enter:</strong></p>
<ul>
<li><strong>Initial deposit</strong> — what you're starting with. (Example: $1,000.)</li>
<li><strong>Monthly deposit</strong> — how much you plan to add each month. (Example: $200.)</li>
<li><strong>Years</strong> — how long you plan to keep saving. (Example: 10 years.)</li>
<li><strong>Annual interest rate</strong> — the rate your savings account or investment actually pays. Check your provider's current rate rather than assuming a long-term average, especially for shorter time frames. (Example: 6%.)</li>
</ul>
<p><strong>How it works</strong><br>We grow your initial deposit and every monthly deposit forward using your interest rate, compounding month by month over the years you enter.</p>
<p><strong>What the result tells you</strong><br>With the example numbers, $1,000 plus $200 a month for 10 years at 6% grows to about $34,600 — of which $25,000 is your own deposits and around $9,600 is interest earned on top.</p>
HTML,
			'help'        => <<<'HTML'
<p>A few ways to use this calculator to hit your goal:</p>
<ol>
<li><strong>Work backwards from your target.</strong> If you have a goal amount in mind, try different monthly deposit figures until the projected total gets close to it.</li>
<li><strong>Small increases add up.</strong> Because of compounding, bumping your monthly deposit up even slightly early on can make a bigger difference than the same increase added later.</li>
<li><strong>Shop around on interest rates.</strong> A better savings rate on the same deposits can meaningfully change your result over several years — it's worth comparing providers.</li>
<li><strong>Keep the goal in view.</strong> Revisit this every few months to check you're still on track, and adjust your monthly deposit if you've fallen behind or gotten ahead.</li>
</ol>
<p><strong>In short:</strong> a simple way to see whether your current saving habit actually gets you where you want to go — and what to change if it doesn't.</p>
HTML,
			'fields'      => array(
				array( 'initial_deposit', 'Initial deposit', '$', '', 1000, 1 ),
				array( 'monthly_deposit', 'Monthly deposit', '$', '', 200, 1 ),
				array( 'years', 'Years', '', 'years', 10, 1 ),
				array( 'annual_rate_percent', 'Annual interest rate', '', '%', 6, 0.1 ),
			),
			'outputs'     => array(
				array( 'future_value', 'Future value' ),
				array( 'total_contributions', 'Total you deposited' ),
				array( 'total_interest', 'Interest earned' ),
			),
		),
	);
}

function bootg_calculators_categories() {
	return array(
		'business' => 'Business calculators',
		'personal' => 'Personal calculators',
	);
}

function bootg_calculators_disclaimer() {
	return 'Figures and results from these calculators are a general guide only and are not financial or professional advice. Consider getting professional advice, such as from a bookkeeper or accountant, before making decisions based on these results.';
}

/** Rough slider min/max for a numeric field — not hand-authored per field, derived from its unit and default so every field gets a sensible drag range. */
function bootg_calc_slider_range( $suffix, $key, $default ) {
	if ( '%' === $suffix ) {
		return array( 0, 100 );
	}
	if ( 'x' === $suffix ) {
		return array( 0, max( 10, $default * 3 ) );
	}
	if ( 'months' === $suffix ) {
		return array( 0, max( 360, $default * 3 ) );
	}
	if ( 'years' === $suffix ) {
		return array( 0, max( 60, $default * 3 ) );
	}
	return array( 0, max( 100, $default * 4 ) );
}

function bootg_calculators_enqueue_assets() {
	wp_enqueue_style( 'bootg-calculators', WEAVIT_ENGINE_URI . 'assets/css/calculators.css', array(), WEAVIT_ENGINE_VERSION );
	wp_enqueue_script( 'bootg-calculators', WEAVIT_ENGINE_URI . 'assets/js/calculators.js', array(), WEAVIT_ENGINE_VERSION, true );
}

/**
 * Renders one calculator's full block: tab bar (Calculator / About this
 * calculator / Help), the calculator form + results + summary, the about
 * copy, the help copy, and the disclaimer. Shared by the shortcode (all 16
 * in one page, JS-switched) and the single-calculator template (one
 * calculator, its own URL). $back is either 'js' (shortcode context — a
 * button that shows the index view again) or a URL string (standalone
 * page context — a real link back to the archive).
 */
function bootg_render_calculator_block( $calc_id, $calc, $back ) {
	ob_start();
	?>
	<div class="bootg-calc-view bootg-calc-view-single" data-view="calc" data-calc="<?php echo esc_attr( $calc_id ); ?>">
		<?php if ( 'js' === $back ) : ?>
			<button type="button" class="bootg-calc-back" data-back="1">&larr; All calculators</button>
		<?php else : ?>
			<a href="<?php echo esc_url( $back ); ?>" class="bootg-calc-back">&larr; All calculators</a>
		<?php endif; ?>

		<h3><?php echo esc_html( $calc['title'] ); ?></h3>
		<p class="bootg-calc-desc"><?php echo esc_html( $calc['description'] ); ?></p>

		<div class="bootg-calc-tabs" role="tablist">
			<button type="button" class="bootg-calc-tab is-active" data-tab="calc">Calculator</button>
			<button type="button" class="bootg-calc-tab" data-tab="about">About this calculator</button>
			<button type="button" class="bootg-calc-tab" data-tab="help">Help</button>
		</div>

		<div class="bootg-calc-tab-panel" data-tab-panel="calc">
			<div class="bootg-calc-body">
				<div class="bootg-calc-fields" data-calc-fields="<?php echo esc_attr( $calc_id ); ?>">
					<?php foreach ( $calc['fields'] as $field ) :
						list( $key, $label, $prefix, $suffix, $default ) = $field;
						$step = isset( $field[5] ) ? $field[5] : 1;
						$type = isset( $field[6] ) ? $field[6] : 'number';
						?>
						<label class="bootg-calc-field">
							<span class="bootg-calc-field-label"><?php echo esc_html( $label ); ?></span>
							<span class="bootg-calc-field-input">
								<?php if ( $prefix ) : ?><span class="bootg-calc-affix"><?php echo esc_html( $prefix ); ?></span><?php endif; ?>
								<?php if ( 'select' === $type ) :
									$options = isset( $field[7] ) ? $field[7] : array();
									?>
									<select data-field="<?php echo esc_attr( $key ); ?>" data-calc="<?php echo esc_attr( $calc_id ); ?>">
										<?php foreach ( $options as $opt_val => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $opt_val, $default ); ?>><?php echo esc_html( $opt_label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="number" inputmode="decimal" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $default ); ?>" data-field="<?php echo esc_attr( $key ); ?>" data-calc="<?php echo esc_attr( $calc_id ); ?>">
								<?php endif; ?>
								<?php if ( $suffix ) : ?><span class="bootg-calc-affix"><?php echo esc_html( $suffix ); ?></span><?php endif; ?>
							</span>
							<?php if ( 'select' !== $type ) :
								list( $range_min, $range_max ) = bootg_calc_slider_range( $suffix, $key, $default );
								?>
								<input type="range" class="bootg-calc-slider" min="<?php echo esc_attr( $range_min ); ?>" max="<?php echo esc_attr( $range_max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $default ); ?>" data-slider-for="<?php echo esc_attr( $key ); ?>" data-calc="<?php echo esc_attr( $calc_id ); ?>">
								<span class="bootg-calc-field-hint">Drag the slider or type an exact amount.</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="bootg-calc-results" data-calc-results="<?php echo esc_attr( $calc_id ); ?>">
					<p class="bootg-calc-summary" data-output="summary"></p>
					<?php foreach ( $calc['outputs'] as $output ) :
						list( $out_key, $out_label ) = $output;
						?>
						<div class="bootg-calc-result-row">
							<span class="bootg-calc-result-label"><?php echo esc_html( $out_label ); ?></span>
							<span class="bootg-calc-result-value" data-output="<?php echo esc_attr( $out_key ); ?>">—</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="bootg-calc-tab-panel" data-tab-panel="about" hidden>
			<?php echo wp_kses_post( $calc['about'] ); ?>
		</div>

		<div class="bootg-calc-tab-panel" data-tab-panel="help" hidden>
			<?php echo wp_kses_post( $calc['help'] ); ?>
		</div>

		<p class="bootg-calc-disclaimer"><?php echo esc_html( bootg_calculators_disclaimer() ); ?></p>
	</div>
	<?php
	return ob_get_clean();
}

function bootg_render_calculators_index( $link_mode = 'js' ) {
	$definitions = bootg_calculators_definitions();
	$categories  = bootg_calculators_categories();
	ob_start();
	?>
	<div class="bootg-calc-view bootg-calc-view-index" data-view="index">
		<?php foreach ( $categories as $cat_key => $cat_label ) : ?>
			<h3 class="bootg-calc-category-title"><?php echo esc_html( $cat_label ); ?></h3>
			<div class="bootg-calc-grid-index">
				<?php foreach ( $definitions as $calc_id => $calc ) :
					if ( $calc['category'] !== $cat_key ) {
						continue;
					}
					?>
					<div class="bootg-calc-card">
						<span class="bootg-calc-badge"><?php echo esc_html( $cat_label ); ?></span>
						<h4><?php echo esc_html( $calc['title'] ); ?></h4>
						<p><?php echo esc_html( $calc['description'] ); ?></p>
						<?php if ( 'js' === $link_mode ) : ?>
							<button type="button" class="bootg-calc-open" data-open="<?php echo esc_attr( $calc_id ); ?>">Open</button>
						<?php else : ?>
							<a href="<?php echo esc_url( bootg_calculator_permalink( $calc_id ) ); ?>" class="bootg-calc-open">Open</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

function bootg_render_calculators_shortcode() {
	bootg_calculators_enqueue_assets();
	$definitions = bootg_calculators_definitions();

	ob_start();
	?>
	<div class="bootg-calc-wrap" data-testid="calculators-widget">
		<?php echo bootg_render_calculators_index( 'js' ); // phpcs:ignore ?>
		<?php foreach ( $definitions as $calc_id => $calc ) : ?>
			<div class="bootg-calc-single-wrap" hidden>
				<?php echo bootg_render_calculator_block( $calc_id, $calc, 'js' ); // phpcs:ignore ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Standalone index page content (the "Calculators" Page) — hero + the index grid, cards linking to each calculator's own permalink. */
function bootg_render_calculators_index_shortcode() {
	bootg_calculators_enqueue_assets();
	ob_start();
	?>
	<section class="bootg-calc-page-hero">
		<div class="bootg-calc-page-hero-inner">
			<p class="bootg-calc-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span>/</span> <span>Calculators</span></p>
			<h1>Free Calculators</h1>
			<p class="bootg-calc-page-hero-sub">Quick tools to check your numbers before you make decisions.</p>
		</div>
	</section>
	<section class="bootg-calc-page-body">
		<div class="bootg-calc-page-body-inner">
			<div class="bootg-calc-wrap" data-testid="calculators-index">
				<?php echo bootg_render_calculators_index( 'url' ); // phpcs:ignore ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ---------------------------------------------------------------------
 * Custom post type: one post per calculator, giving each its own real,
 * bookmarkable, shareable URL (e.g. /calculators/gst/). The index at
 * /calculators/ stays a normal WP Page (using this theme's existing
 * page-full-width template, the same proven path as every other
 * shortcode-driven page) rather than a CPT archive — block themes route
 * CPT archives through the active theme's own archive.html, which this
 * theme already dedicates to blog-style listings, so a real Page avoids
 * fighting that. Both the Page and the calculator posts are
 * self-created by this plugin, so a fresh WordPress site gets the whole
 * thing (routing, content, assets) just by activating the plugin.
 * ------------------------------------------------------------------- */

function bootg_register_calculator_cpt() {
	register_post_type( 'bootg_calculator', array(
		'labels'       => array(
			'name'          => 'Calculators',
			'singular_name' => 'Calculator',
		),
		'public'       => true,
		'show_ui'      => false,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'calculators', 'with_front' => false ),
		'supports'     => array( 'title', 'editor' ),
		'show_in_rest' => false,
	) );
}

/**
 * Injects a calculator's markup in place of its (empty) stored content —
 * the same `the_content` path every normal post/page already renders
 * through, so it inherits the active theme's header/footer automatically
 * via whatever template the post is assigned (see bootg_calculators_sync_posts()).
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'bootg_calculator' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$calc_id     = get_post()->post_name;
	$definitions = bootg_calculators_definitions();
	if ( ! isset( $definitions[ $calc_id ] ) ) {
		return $content;
	}
	bootg_calculators_enqueue_assets();
	$calc = $definitions[ $calc_id ];
	ob_start();
	?>
	<section class="bootg-calc-page-hero">
		<div class="bootg-calc-page-hero-inner">
			<p class="bootg-calc-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span>/</span> <a href="<?php echo esc_url( bootg_calculators_archive_url() ); ?>">Calculators</a> <span>/</span> <span><?php echo esc_html( $calc['title'] ); ?></span></p>
			<h1><?php echo esc_html( $calc['title'] ); ?></h1>
			<p class="bootg-calc-page-hero-sub"><?php echo esc_html( $calc['description'] ); ?></p>
		</div>
	</section>
	<section class="bootg-calc-page-body">
		<div class="bootg-calc-page-body-inner">
			<div class="bootg-calc-wrap" data-testid="calculator-single">
				<?php echo bootg_render_calculator_block( $calc_id, $calc, bootg_calculators_archive_url() ); // phpcs:ignore ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
} );

/**
 * Keeps the `bootg_calculator` posts (and the "Calculators" index Page)
 * in sync with the catalogue above — created once and left alone after
 * that (so a site owner could retitle one without it being overwritten).
 * Gated behind an option + version bump so this doesn't run a query on
 * every request.
 */
function bootg_calculators_sync_posts() {
	$version = '3';
	if ( get_option( 'bootg_calculators_synced_version' ) === $version ) {
		return;
	}

	foreach ( bootg_calculators_definitions() as $calc_id => $calc ) {
		$existing = get_page_by_path( $calc_id, OBJECT, 'bootg_calculator' );
		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', 'page-full-width' );
			continue;
		}
		$post_id = wp_insert_post( array(
			'post_type'   => 'bootg_calculator',
			'post_title'  => $calc['title'],
			'post_name'   => $calc_id,
			'post_status' => 'publish',
		) );
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_wp_page_template', 'page-full-width' );
		}
	}

	$index_page = get_page_by_path( 'calculators', OBJECT, 'page' );
	if ( ! $index_page ) {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_title'   => 'Calculators',
			'post_name'    => 'calculators',
			'post_status'  => 'publish',
			'post_content' => '[bootg_calculators_index]',
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-full-width' );
		}
	} elseif ( false === strpos( $index_page->post_content, 'bootg_calculators_index' ) ) {
		wp_update_post( array(
			'ID'           => $index_page->ID,
			'post_content' => '[bootg_calculators_index]',
		) );
		update_post_meta( $index_page->ID, '_wp_page_template', 'page-full-width' );
	}

	update_option( 'bootg_calculators_synced_version', $version );
	flush_rewrite_rules();
}

function bootg_calculator_permalink( $calc_id ) {
	$post = get_page_by_path( $calc_id, OBJECT, 'bootg_calculator' );
	return $post ? get_permalink( $post ) : home_url( '/calculators/' );
}

function bootg_calculators_archive_url() {
	$page = get_page_by_path( 'calculators', OBJECT, 'page' );
	return $page ? get_permalink( $page ) : home_url( '/calculators/' );
}
