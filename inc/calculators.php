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
			'about'       => "Break-even tells you the point where your sales exactly cover your costs — no profit, no loss. Anything you sell past that point is where profit starts. It's the first number most businesses should know before setting a sales target or launching a new product.",
			'help'        => 'Fixed costs are the costs that stay the same regardless of sales — rent, insurance, salaries. Variable cost per unit is what it costs you to produce or deliver one more unit (materials, packaging, direct labour). Drag the sliders or type exact numbers — the result updates instantly either way.',
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
			'about'       => "Burn rate is how fast your business is spending cash. If your expenses are higher than your revenue, every month eats into your cash balance — this calculator shows you how many months you have left at the current rate, so you can act before it becomes urgent.",
			'help'        => "Monthly expenses should include everything leaving the business each month — wages, rent, subscriptions, stock. Monthly revenue is what's actually coming in. If revenue is higher than expenses, your burn rate is effectively zero and your runway is unlimited.",
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
			'about'       => "This calculator uses a standard amortising loan formula to estimate your monthly repayment and how much of what you pay back is interest versus principal, based on the amount borrowed, the rate, and the term.",
			'help'        => "Use the annual interest rate the lender quotes you (not the monthly rate — this calculator converts it for you). Term is the full length of the loan in months (e.g. a 3-year loan is 36 months).",
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
			'about'       => "A quick, rough-order valuation using the earnings-multiple method: annual profit (often called SDE — Seller's Discretionary Earnings for small businesses) multiplied by an industry-typical multiple. It's a starting point for a conversation, not a formal valuation.",
			'help'        => "Annual profit should be what the business actually earns after all costs, including a reasonable owner's wage if you pay yourself one. The multiple varies a lot by industry — ask your bookkeeper or an industry body what's typical for yours; 2-4x is common for small service businesses.",
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
			'about'       => "The current ratio is a classic liquidity check: can you cover what you owe in the next 12 months with what you can turn into cash in the next 12 months? A ratio above 1 means yes, in theory — well above 1 is a healthier cushion.",
			'help'        => "Current assets: cash, money owed to you (debtors), and stock you'd sell within a year. Current liabilities: money you owe within a year — supplier bills, short-term loans, upcoming tax. Both figures should be on your latest balance sheet.",
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
			'about'       => "Discounts move more than price — they eat straight into your margin. This calculator shows the discounted price plus your margin before and after, so you can see exactly how much room you actually have before a discount stops being worth it.",
			'help'        => "Cost per unit is what the item costs you (not what you sell it for). Margin is shown as a percentage of the selling price, so you can compare the discounted margin against your usual target margin at a glance.",
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
			'about'       => "Buying with a loan and leasing both get you the equipment, but they cost differently over the full term. This compares the total amount you'd actually pay out under each option so you can see which is cheaper over the life of the agreement.",
			'help'        => "For the loan side, enter the equipment cost, the rate, and the loan term — the calculator works out the repayments itself. For the lease side, enter the monthly payment your lease quote gives you and its term directly.",
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
			'about'       => "A faster version of break-even for when you already know your overall gross margin percentage rather than per-unit costs — useful for service businesses or businesses with many different products.",
			'help'        => "Gross margin is (revenue − cost of goods sold) ÷ revenue, as a percentage. You'll usually find this on a recent profit and loss report, or your bookkeeper can confirm it for you.",
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
			'about'       => "Rather than guessing a price, this works backwards from the margin you want to protect, so your price is always built on a number you actually chose rather than a round figure that happens to look right.",
			'help'        => "Desired margin is the percentage of the selling price you want left as profit after covering the unit cost. Keep it below 100% — a margin of 100% or more isn't mathematically possible with this formula.",
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
			'about'       => "Small changes compound. This lets you test the combined effect of a modest price rise, a cost saving, and a bit more volume together, so you can see which lever actually moves your profit the most before you commit to one.",
			'help'        => "Enter your current annual revenue and costs, then set the percentage change you're considering for each lever. Set any lever to 0% to isolate the effect of just the others.",
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
			'about'       => "Most new businesses underestimate what it actually costs to open the doors. This adds up the common categories in one place so you have a realistic total to plan or budget against before you start spending.",
			'help'        => "Working capital is cash set aside to cover day-to-day costs (rent, wages, stock) while the business is still building up its own income — most advisors suggest budgeting at least 3-6 months of running costs here.",
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
			'about'       => "Australia's GST rate is a flat 10%. This calculator either adds GST to a GST-exclusive price (to get what to charge) or strips GST out of a GST-inclusive price (to see the pre-tax amount) — useful for invoicing and for checking figures at tax time.",
			'help'        => "Choose \"Add GST\" if your amount doesn't include GST yet (e.g. your quoted cost price). Choose \"Remove GST\" if your amount already includes GST (e.g. a receipt total) and you want to know the GST component and the net amount.",
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
			'about'       => "Credit card interest compounds monthly, so paying only slightly above the minimum can take far longer — and cost far more — than people expect. This shows the real timeline and total interest at a fixed monthly payment.",
			'help'        => "APR is the annual interest rate printed on your card statement. If your chosen monthly payment is too low to even cover the interest being charged, the balance will never clear — the calculator will tell you if that's the case.",
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
			'about'       => "Compound interest means small, regular contributions can grow into a large balance over a long enough time. This projects where your current savings plus ongoing contributions could land by the time you retire.",
			'help'        => "Expected annual return depends on how the savings are invested — conservative options tend to average lower returns, growth-oriented options higher but with more year-to-year movement. If unsure, a long-term average like 6-8% is a common assumption, not a guarantee.",
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
			'about'       => "Once you stop contributing and start withdrawing, the question flips: how long will the balance actually last? This models your savings shrinking (or growing) each year as withdrawals and investment returns both apply.",
			'help'        => "If your annual withdrawal is lower than what the balance earns in investment growth each year, the savings technically never run out — the calculator will tell you when that's the case.",
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
			'about'       => "A general-purpose savings projection — not specific to retirement — for any goal where you're depositing a lump sum plus regular monthly amounts and want to know what it grows into by a target date.",
			'help'        => "Annual interest rate should reflect the account or investment you're actually using — check your provider's current rate rather than assuming a long-term average for shorter time frames.",
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
			<p><?php echo esc_html( $calc['about'] ); ?></p>
		</div>

		<div class="bootg-calc-tab-panel" data-tab-panel="help" hidden>
			<p><?php echo esc_html( $calc['help'] ); ?></p>
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
