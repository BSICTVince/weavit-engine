<?php
/**
 * Forms engine — front-end rendering. The plugin owns the structural
 * <form> wrapper (nonce, honeypot, hidden fields, submit button) and a
 * generic fallback field renderer so the engine produces a working form
 * with zero theme involvement. A theme that wants its own field markup
 * (grid layout, its own CSS framework, etc.) hooks `weavit_render_form_fields`
 * and its output replaces the fallback entirely — see
 * inc/forms/forms-render.php in the Bookkeeping On The Go theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	foreach ( bootg_get_all_forms() as $form ) {
		$tag = bootg_form_shortcode_tag( $form->ID, $form->post_title );
		add_shortcode( $tag, function () use ( $form ) {
			return bootg_render_form( $form->ID );
		} );
	}
}, 20 );

function bootg_render_form( $form_id ) {
	$form_id = absint( $form_id );
	$form    = get_post( $form_id );
	if ( ! $form || 'bootg_form' !== $form->post_type ) {
		return '';
	}

	$fields   = bootg_get_form_schema( $form_id );
	$settings = bootg_get_form_settings( $form_id );

	if ( ! $fields ) {
		return '';
	}

	$status = '';
	if ( isset( $_GET['bootg_form'] ) && (int) $_GET['bootg_form'] === $form_id ) {
		if ( 'success' === ( $_GET['status'] ?? '' ) ) {
			$status = '<p class="bootg-form-msg bootg-form-msg--success" role="status">' . esc_html( $settings['success_message'] ) . '</p>';
		} elseif ( 'recaptcha' === ( $_GET['status'] ?? '' ) ) {
			$status = '<p class="bootg-form-msg bootg-form-msg--error" role="alert">We couldn\'t verify you\'re human — please try again.</p>';
		} elseif ( 'error' === ( $_GET['status'] ?? '' ) ) {
			$status = '<p class="bootg-form-msg bootg-form-msg--error" role="alert">Please check the required fields and try again.</p>';
		}
	}

	ob_start();
	?>
	<form class="bootg-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php wp_nonce_field( 'bootg_form_submit_' . $form_id ); ?>
		<input type="hidden" name="action" value="bootg_form_submit">
		<input type="hidden" name="bootg_form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( bootg_current_url() ); ?>">
		<p style="position:absolute;left:-9999px;" aria-hidden="true">
			<label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		</p>

		<?php echo bootg_render_form_fields( $form_id, $fields ); // phpcs:ignore ?>
		<?php echo bootg_render_recaptcha_field(); // phpcs:ignore ?>

		<button type="submit" class="<?php echo esc_attr( apply_filters( 'weavit_form_submit_class', 'bootg-form-submit', $form_id ) ); ?>"><?php echo esc_html( $settings['submit_label'] ); ?></button>
		<?php echo $status; // phpcs:ignore ?>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Delegates field-list markup to whatever the active theme (or another
 * plugin) hooks onto `weavit_render_form_fields`. Falls back to a plain,
 * unstyled renderer so the engine still works with no theme involvement.
 */
function bootg_render_form_fields( $form_id, $fields ) {
	if ( has_filter( 'weavit_render_form_fields' ) ) {
		return apply_filters( 'weavit_render_form_fields', '', $form_id, $fields );
	}
	return bootg_render_form_fields_fallback( $form_id, $fields );
}

function bootg_render_form_fields_fallback( $form_id, $fields ) {
	$out = '';
	foreach ( $fields as $field ) {
		if ( 'checkbox' === $field['type'] ) {
			$out .= bootg_render_checkbox_field_fallback( $form_id, $field );
		} else {
			$out .= '<p class="bootg-form-field">' . bootg_render_form_field_inner_fallback( $form_id, $field ) . '</p>';
		}
	}
	return $out;
}

function bootg_render_checkbox_field_fallback( $form_id, $field ) {
	$id       = 'bootg_field_' . $form_id . '_' . $field['field_id'];
	$name     = 'bootg_field[' . $field['field_id'] . ']';
	$required = ! empty( $field['required'] );

	ob_start();
	?>
	<p class="bootg-form-field bootg-form-field--checkbox">
		<label>
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php echo $required ? 'required' : ''; ?>>
			<?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?>
		</label>
	</p>
	<?php
	return ob_get_clean();
}

function bootg_render_form_field_inner_fallback( $form_id, $field ) {
	$id       = 'bootg_field_' . $form_id . '_' . $field['field_id'];
	$name     = 'bootg_field[' . $field['field_id'] . ']';
	$label    = $field['label'];
	$required = ! empty( $field['required'] );

	ob_start();
	?>
	<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
	<?php if ( 'textarea' === $field['type'] ) : ?>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="5" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"></textarea>
	<?php elseif ( 'select' === $field['type'] ) : ?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>>
			<option value="">Select&hellip;</option>
			<?php foreach ( (array) ( $field['options'] ?? array() ) as $opt ) : ?>
				<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php else :
		$type = in_array( $field['type'], array( 'email', 'tel', 'number' ), true ) ? $field['type'] : 'text';
		?>
		<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
	<?php endif; ?>
	<?php
	return ob_get_clean();
}
