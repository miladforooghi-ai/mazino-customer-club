<?php
/**
 * Shared markup renderer for the Elementor widget and the shortcode.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Form' ) ) {
	return;
}

/**
 * Renders the signup form and the thank-you block.
 */
class MCC_Form {

	/**
	 * Instance counter for unique element ids.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Default texts and options.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'show_image'        => true,
			'image_id'          => 0,
			'image_url'         => '',
			'image_position'    => 'top',
			'heading'           => 'به باشگاه مشتریان بپیوندید',
			'heading_tag'       => 'h2',
			'description'       => 'با عضویت در باشگاه مشتریان، پیش از همه از تخفیف‌ها و پیشنهادهای ویژه باخبر شوید.',
			'show_name'         => true,
			'label_name'        => 'نام و نام خانوادگی',
			'placeholder_name'  => 'مثلاً سارا محمدی',
			'show_phone'        => true,
			'label_phone'       => 'شماره تلفن همراه',
			'placeholder_phone' => '۰۹۱۲۱۲۳۴۵۶۷',
			'show_birth'        => true,
			'label_birth'       => 'تاریخ تولد',
			'date_order'        => 'dmy',
			'years_back'        => 100,
			'required_marks'    => true,
			'show_consent'      => false,
			'consent_text'      => 'با عضویت در باشگاه مشتریان، دریافت پیامک‌های اطلاع‌رسانی را می‌پذیرم.',
			'button_text'       => 'عضویت در باشگاه مشتریان',
			'button_full'       => true,
			'thanks_title'      => 'از عضویت شما سپاسگزاریم!',
			'thanks_message'    => 'نام شما با موفقیت در باشگاه مشتریان ثبت شد. به‌زودی پیشنهادهای ویژه را برایتان می‌فرستیم.',
			'show_thanks_image' => false,
			'thanks_image_id'   => 0,
			'thanks_image_url'  => '',
			'again_text'        => 'ثبت‌نام یک نفر دیگر',
			'error_message'     => 'ثبت‌نام انجام نشد. لطفاً اطلاعات را بررسی کنید و دوباره تلاش کنید.',
			'duplicate_message' => 'این شماره تلفن قبلاً در باشگاه مشتریان ثبت شده است.',
			'redirect_url'      => '',
		);
	}

	/**
	 * Build an <img> from an attachment id or a URL.
	 *
	 * @param int    $id       Attachment id.
	 * @param string $url      Fallback URL.
	 * @param string $size     Image size.
	 * @param array  $attrs    Extra attributes.
	 * @return string
	 */
	private static function image( $id, $url, $size = 'large', $attrs = array() ) {
		$id = (int) $id;

		if ( $id ) {
			$html = wp_get_attachment_image( $id, $size, false, $attrs );

			if ( $html ) {
				return $html;
			}
		}

		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$extra = '';

		foreach ( $attrs as $name => $value ) {
			$extra .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return '<img src="' . esc_url( $url ) . '" loading="lazy" decoding="async"' . $extra . '>';
	}

	/**
	 * Render the widget output.
	 *
	 * @param array $args Overrides for the defaults.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$args = array_merge( self::defaults(), array_filter( (array) $args, 'is_scalar' ) );

		self::$counter++;
		$uid = 'mcc-' . self::$counter;

		$position = in_array( $args['image_position'], array( 'top', 'left', 'right' ), true ) ? $args['image_position'] : 'top';
		$dir      = is_rtl() ? 'rtl' : 'ltr';

		$heading_tag = strtolower( (string) $args['heading_tag'] );

		if ( ! in_array( $heading_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span' ), true ) ) {
			$heading_tag = 'h2';
		}

		$heading     = trim( (string) $args['heading'] );
		$description = trim( (string) $args['description'] );
		$button_text = trim( (string) $args['button_text'] );

		if ( '' === $button_text ) {
			$button_text = self::defaults()['button_text'];
		}

		$image = $args['show_image'] ? self::image( $args['image_id'], $args['image_url'], 'large', array( 'class' => 'mcc-img' ) ) : '';

		$thanks_image = $args['show_thanks_image'] ? self::image( $args['thanks_image_id'], $args['thanks_image_url'], 'medium', array( 'class' => 'mcc-thanks-img' ) ) : '';

		$wrap_classes = array( 'mcc-wrap' );

		if ( '' !== $image ) {
			$wrap_classes[] = 'top' === $position ? 'mcc-is-top' : 'mcc-is-side';
			$wrap_classes[] = 'mcc-is-media-' . $position;
		}

		if ( $args['button_full'] ) {
			$wrap_classes[] = 'mcc-btn-full';
		}

		$fields = array();

		if ( $args['show_name'] ) {
			$fields[] = 'name';
		}

		if ( $args['show_phone'] ) {
			$fields[] = 'phone';
		}

		if ( $args['show_birth'] ) {
			$fields[] = 'birth';
		}

		if ( $args['show_consent'] ) {
			$fields[] = 'consent';
		}

		$today        = MCC_Jalali::today();
		$years_back   = max( 1, min( 120, (int) $args['years_back'] ) );
		$first_year   = $today['jy'] - $years_back + 1;
		$date_order   = 'ymd' === $args['date_order'] ? 'ymd' : 'dmy';
		$required     = $args['required_marks'] ? '<span class="mcc-req" aria-hidden="true">*</span>' : '';
		$months       = MCC_Jalali::months();
		$phone_label  = trim( (string) $args['label_phone'] );
		$name_label   = trim( (string) $args['label_name'] );
		$birth_label  = trim( (string) $args['label_birth'] );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( implode( ' ', $wrap_classes ) ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
			<div class="mcc-card">
				<?php if ( '' !== $image ) : ?>
					<div class="mcc-media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with escaping. ?></div>
				<?php endif; ?>

				<div class="mcc-body">
					<?php if ( '' !== $heading ) : ?>
						<<?php echo esc_html( $heading_tag ); ?> class="mcc-heading"><?php echo esc_html( $heading ); ?></<?php echo esc_html( $heading_tag ); ?>>
					<?php endif; ?>

					<?php if ( '' !== $description ) : ?>
						<div class="mcc-desc"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
					<?php endif; ?>

					<form class="mcc-form" method="post" novalidate
						data-mcc-form="1"
						data-fields="<?php echo esc_attr( implode( ',', $fields ) ); ?>"
						data-error="<?php echo esc_attr( $args['error_message'] ); ?>"
						data-duplicate="<?php echo esc_attr( $args['duplicate_message'] ); ?>"
						<?php if ( '' !== trim( (string) $args['redirect_url'] ) ) : ?>
							data-redirect="<?php echo esc_url( $args['redirect_url'] ); ?>"
						<?php endif; ?>
					>
						<input type="hidden" name="action" value="mcc_register">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mcc_register' ) ); ?>">
						<input type="hidden" name="fields" value="<?php echo esc_attr( implode( ',', $fields ) ); ?>">
						<input type="hidden" name="source" value="">

						<div class="mcc-hp" aria-hidden="true">
							<label for="<?php echo esc_attr( $uid ); ?>-website">وب‌سایت</label>
							<input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="mcc_website" tabindex="-1" autocomplete="off">
						</div>

						<?php if ( $args['show_name'] ) : ?>
							<div class="mcc-field" data-field="name">
								<label class="mcc-label" for="<?php echo esc_attr( $uid ); ?>-name"><?php echo esc_html( $name_label ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
								<input type="text" class="mcc-input" id="<?php echo esc_attr( $uid ); ?>-name" name="name"
									placeholder="<?php echo esc_attr( $args['placeholder_name'] ); ?>"
									autocomplete="name" maxlength="120" required>
								<span class="mcc-error" aria-live="polite"></span>
							</div>
						<?php endif; ?>

						<?php if ( $args['show_phone'] ) : ?>
							<div class="mcc-field" data-field="phone">
								<label class="mcc-label" for="<?php echo esc_attr( $uid ); ?>-phone"><?php echo esc_html( $phone_label ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
								<input type="tel" class="mcc-input mcc-ltr" id="<?php echo esc_attr( $uid ); ?>-phone" name="phone"
									placeholder="<?php echo esc_attr( $args['placeholder_phone'] ); ?>"
									inputmode="numeric" autocomplete="tel" maxlength="20" dir="ltr" required>
								<span class="mcc-error" aria-live="polite"></span>
							</div>
						<?php endif; ?>

						<?php if ( $args['show_birth'] ) : ?>
							<div class="mcc-field" data-field="birth">
								<span class="mcc-label" id="<?php echo esc_attr( $uid ); ?>-birth-label"><?php echo esc_html( $birth_label ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

								<div class="mcc-date" role="group" aria-labelledby="<?php echo esc_attr( $uid ); ?>-birth-label">
									<?php
									$parts = array(
										'day'   => '<select name="bday" class="mcc-select" data-part="day" aria-label="روز"><option value="">روز</option>',
										'month' => '<select name="bmonth" class="mcc-select" data-part="month" aria-label="ماه"><option value="">ماه</option>',
										'year'  => '<select name="byear" class="mcc-select" data-part="year" aria-label="سال"><option value="">سال</option>',
									);

									$order = 'ymd' === $date_order ? array( 'year', 'month', 'day' ) : array( 'day', 'month', 'year' );

									foreach ( $order as $part ) {
										echo $parts[ $part ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe static markup.

										if ( 'day' === $part ) {
											for ( $d = 1; $d <= 31; $d++ ) {
												printf( '<option value="%1$d">%2$s</option>', (int) $d, esc_html( MCC_Helpers::to_persian_digits( (string) $d ) ) );
											}
										} elseif ( 'month' === $part ) {
											foreach ( $months as $index => $month_name ) {
												printf( '<option value="%1$d">%2$s</option>', (int) $index, esc_html( $month_name ) );
											}
										} else {
											for ( $y = $today['jy']; $y >= $first_year; $y-- ) {
												printf( '<option value="%1$d">%2$s</option>', (int) $y, esc_html( MCC_Helpers::to_persian_digits( (string) $y ) ) );
											}
										}

										echo '</select>';
									}
									?>
								</div>
								<span class="mcc-error" aria-live="polite"></span>
							</div>
						<?php endif; ?>

						<?php if ( $args['show_consent'] ) : ?>
							<div class="mcc-field mcc-field-consent" data-field="consent">
								<label class="mcc-consent">
									<input type="checkbox" name="consent" value="1">
									<span class="mcc-consent-text"><?php echo wp_kses_post( $args['consent_text'] ); ?></span>
								</label>
								<span class="mcc-error" aria-live="polite"></span>
							</div>
						<?php endif; ?>

						<button type="submit" class="mcc-btn">
							<span class="mcc-btn-label"><?php echo esc_html( $button_text ); ?></span>
							<span class="mcc-spinner" aria-hidden="true"></span>
						</button>

						<div class="mcc-alert" role="alert" aria-live="assertive"></div>
					</form>

					<div class="mcc-success" hidden>
						<span class="mcc-success-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 12.5l5 5L20 6.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</span>

						<?php if ( '' !== $thanks_image ) : ?>
							<div class="mcc-thanks-media"><?php echo $thanks_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>

						<h3 class="mcc-success-title"><?php echo esc_html( $args['thanks_title'] ); ?></h3>
						<div class="mcc-success-text"><?php echo wp_kses_post( wpautop( $args['thanks_message'] ) ); ?></div>

						<?php if ( '' !== trim( (string) $args['again_text'] ) ) : ?>
							<button type="button" class="mcc-again"><?php echo esc_html( $args['again_text'] ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
