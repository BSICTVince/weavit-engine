<?php
/**
 * Business + personal calculators widget, built in-house rather than an
 * embedded third-party iframe. All math runs client-side (assets/js/
 * calculators.js); this file only owns the calculator catalogue (titles,
 * descriptions, input fields, output rows) and renders the index + each
 * calculator's form/result markup. One shortcode, [bootg_calculators],
 * drops the whole widget anywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	add_shortcode( 'bootg_calculators_widget', 'bootg_render_calculators_shortcode' );
} );

/**
 * Catalogue of every calculator: id, category, title, description, the
 * input fields (key, label, unit prefix/suffix, default, step), and the
 * output rows (key, label) that calculators.js fills in. Field/output
 * keys are what the JS reads/writes via data-field / data-output, so they
 * must match the case IDs in calculators.js exactly.
 */
function bootg_calculators_definitions() {
	return array(
		'break-even'             => array(
			'category'    => 'business',
			'title'       => 'Break-even',
			'description' => 'Work out how many units you need to sell, or how much revenue you need, to cover your fixed and variable costs.',
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
		'burn-rate'              => array(
			'category'    => 'business',
			'title'       => 'Burn rate',
			'description' => 'Monitor your cash burn and see how many months your current cash reserves will last at your current spending rate.',
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
		'business-loan'          => array(
			'category'    => 'business',
			'title'       => 'Business loan',
			'description' => 'Calculate the monthly repayment, total interest, and total repayment for a business loan.',
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
		'business-value'         => array(
			'category'    => 'business',
			'title'       => 'Business value',
			'description' => 'Get a rough valuation estimate of your business based on annual profit and a chosen earnings multiple.',
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
		'current-ratio'          => array(
			'category'    => 'business',
			'title'       => 'Current ratio',
			'description' => "Check your short-term financial health: do you have enough current assets to cover current liabilities?",
			'fields'      => array(
				array( 'current_assets', 'Current assets', '$', '', 80000, 1 ),
				array( 'current_liabilities', 'Current liabilities', '$', '', 40000, 1 ),
			),
			'outputs'     => array(
				array( 'ratio', 'Current ratio' ),
				array( 'reading', 'What this means' ),
			),
		),
		'discount'               => array(
			'category'    => 'business',
			'title'       => 'Discount',
			'description' => 'See how a product discount affects your selling price and profit margin.',
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
		'equipment-loan-lease'   => array(
			'category'    => 'business',
			'title'       => 'Equipment loan vs lease',
			'description' => 'Compare the total lifetime cost of financing equipment with a loan versus leasing it.',
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
			'fields'      => array(
				array( 'fixed_costs', 'Fixed costs', '$', '', 15000, 1 ),
				array( 'gross_margin_percent', 'Gross margin', '', '%', 40, 1 ),
			),
			'outputs'     => array(
				array( 'revenue', 'Break-even revenue' ),
			),
		),
		'product-pricing'        => array(
			'category'    => 'business',
			'title'       => 'Product pricing',
			'description' => 'Set a selling price from your unit cost and desired profit margin.',
			'fields'      => array(
				array( 'cost_per_unit', 'Cost per unit', '$', '', 25, 1 ),
				array( 'desired_margin_percent', 'Desired margin', '', '%', 35, 1 ),
			),
			'outputs'     => array(
				array( 'selling_price', 'Selling price' ),
				array( 'markup_percent', 'Markup on cost' ),
			),
		),
		'profit-improvement'     => array(
			'category'    => 'business',
			'title'       => 'Profit improvement',
			'description' => 'Model how small changes to price, cost and volume affect your bottom line.',
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
		'start-up-costs'         => array(
			'category'    => 'business',
			'title'       => 'Start-up costs',
			'description' => 'Add up everything you need to launch: equipment, inventory, licensing, marketing and working capital.',
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
		'gst'                    => array(
			'category'    => 'business',
			'title'       => 'GST calculator',
			'description' => 'Quickly add or remove 10% GST from an Australian price.',
			'fields'      => array(
				array( 'amount', 'Amount', '$', '', 100, 1 ),
				array( 'direction', 'I want to', '', '', 'add', 0, 'select', array(
					'add'    => 'Add GST (price excludes GST)',
					'remove' => 'Remove GST (price includes GST)',
				) ),
			),
			'outputs'     => array(
				array( 'gst_amount', 'GST amount (10%)' ),
				array( 'result', 'Result' ),
			),
		),
		'credit-card-repayment'  => array(
			'category'    => 'personal',
			'title'       => 'Credit card repayment',
			'description' => 'Find out how long it will take to pay off your credit card at a fixed monthly payment, and how much interest you will pay.',
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
		'retirement-savings'     => array(
			'category'    => 'personal',
			'title'       => 'Retirement savings',
			'description' => 'Project how your retirement savings will grow with regular contributions and compound interest.',
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
		'retirement-drawdown'    => array(
			'category'    => 'personal',
			'title'       => 'Retirement drawdown',
			'description' => 'See how many years your retirement savings will last at a given annual withdrawal rate.',
			'fields'      => array(
				array( 'savings_balance', 'Savings balance', '$', '', 600000, 1 ),
				array( 'annual_withdrawal', 'Annual withdrawal', '$', '', 40000, 1 ),
				array( 'annual_return_percent', 'Expected annual return', '', '%', 5, 0.1 ),
			),
			'outputs'     => array(
				array( 'years', 'Years the savings will last' ),
			),
		),
		'savings-future-value'   => array(
			'category'    => 'personal',
			'title'       => 'Savings future value',
			'description' => 'Model how regular savings deposits grow over time with compound interest.',
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

function bootg_render_calculators_shortcode() {
	wp_enqueue_style( 'bootg-calculators', WEAVIT_ENGINE_URI . 'assets/css/calculators.css', array(), WEAVIT_ENGINE_VERSION );
	wp_enqueue_script( 'bootg-calculators', WEAVIT_ENGINE_URI . 'assets/js/calculators.js', array(), WEAVIT_ENGINE_VERSION, true );

	$definitions = bootg_calculators_definitions();
	$categories  = bootg_calculators_categories();

	ob_start();
	?>
	<div class="bootg-calc-wrap" data-testid="calculators-widget">

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
							<button type="button" class="bootg-calc-open" data-open="<?php echo esc_attr( $calc_id ); ?>">Open</button>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $definitions as $calc_id => $calc ) : ?>
			<div class="bootg-calc-view bootg-calc-view-single" data-view="calc" data-calc="<?php echo esc_attr( $calc_id ); ?>" hidden>
				<button type="button" class="bootg-calc-back" data-back="1">&larr; All calculators</button>
				<h3><?php echo esc_html( $calc['title'] ); ?></h3>
				<p class="bootg-calc-desc"><?php echo esc_html( $calc['description'] ); ?></p>

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
							</label>
						<?php endforeach; ?>
					</div>

					<div class="bootg-calc-results" data-calc-results="<?php echo esc_attr( $calc_id ); ?>">
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
		<?php endforeach; ?>

	</div>
	<?php
	return ob_get_clean();
}
