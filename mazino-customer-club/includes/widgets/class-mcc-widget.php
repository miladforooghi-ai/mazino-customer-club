<?php
/**
 * Elementor widget: customer club signup form.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) || class_exists( 'MCC_Elementor_Widget' ) ) {
	return;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;

/**
 * Fully editable signup form for the Elementor editor.
 */
class MCC_Elementor_Widget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mcc_customer_club';
	}

	/**
	 * Widget title in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return 'باشگاه مشتریان';
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Panel category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'mazino' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'باشگاه', 'مشتریان', 'فرم', 'عضویت', 'ثبت نام', 'club', 'form' );
	}

	/**
	 * Frontend styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'mcc-frontend' );
	}

	/**
	 * Frontend scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'mcc-frontend' );
	}

	/**
	 * Register all controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_image_controls();
		$this->register_text_controls();
		$this->register_field_controls();
		$this->register_consent_controls();
		$this->register_button_controls();
		$this->register_thanks_controls();

		$this->register_card_style();
		$this->register_image_style();
		$this->register_heading_style();
		$this->register_description_style();
		$this->register_label_style();
		$this->register_input_style();
		$this->register_button_style();
		$this->register_consent_style();
		$this->register_success_style();
	}

	/**
	 * Content tab: image.
	 *
	 * @return void
	 */
	private function register_image_controls() {
		$this->start_controls_section(
			'section_image',
			array(
				'label' => 'تصویر',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'        => 'نمایش تصویر',
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'image',
			array(
				'label'     => 'انتخاب تصویر',
				'type'      => Controls_Manager::MEDIA,
				'default'   => array( 'url' => Utils::get_placeholder_image_src() ),
				'condition' => array( 'show_image' => 'yes' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'image_position',
			array(
				'label'     => 'جای تصویر',
				'type'      => Controls_Manager::SELECT,
				'default'   => 'top',
				'options'   => array(
					'top'   => 'بالای فرم',
					'right' => 'سمت راست فرم',
					'left'  => 'سمت چپ فرم',
				),
				'condition' => array(
					'show_image'     => 'yes',
					'image[url]!'    => '',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab: heading and description.
	 *
	 * @return void
	 */
	private function register_text_controls() {
		$this->start_controls_section(
			'section_text',
			array(
				'label' => 'عنوان و توضیح',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'heading',
			array(
				'label'       => 'عنوان',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'به باشگاه مشتریان بپیوندید',
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'heading_tag',
			array(
				'label'   => 'تگ عنوان',
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
			)
		);

		$this->add_control(
			'description',
			array(
				'label'   => 'توضیح کوتاه',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => 'با عضویت در باشگاه مشتریان، پیش از همه از تخفیف‌ها و پیشنهادهای ویژه باخبر شوید.',
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab: form fields.
	 *
	 * @return void
	 */
	private function register_field_controls() {
		$this->start_controls_section(
			'section_fields',
			array(
				'label' => 'فیلدهای فرم',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'required_marks',
			array(
				'label'        => 'نمایش ستارهٔ فیلدهای اجباری',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'heading_name',
			array(
				'label'     => 'نام',
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_name',
			array(
				'label'        => 'نمایش فیلد نام',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'label_name',
			array(
				'label'       => 'برچسب',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'نام و نام خانوادگی',
				'label_block' => true,
				'condition'   => array( 'show_name' => 'yes' ),
			)
		);

		$this->add_control(
			'placeholder_name',
			array(
				'label'       => 'متن راهنما (Placeholder)',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'مثلاً سارا محمدی',
				'label_block' => true,
				'condition'   => array( 'show_name' => 'yes' ),
			)
		);

		$this->add_control(
			'heading_phone',
			array(
				'label'     => 'شماره تلفن',
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_phone',
			array(
				'label'        => 'نمایش فیلد شماره تلفن',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'label_phone',
			array(
				'label'       => 'برچسب',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'شماره تلفن همراه',
				'label_block' => true,
				'condition'   => array( 'show_phone' => 'yes' ),
			)
		);

		$this->add_control(
			'placeholder_phone',
			array(
				'label'       => 'متن راهنما (Placeholder)',
				'type'        => Controls_Manager::TEXT,
				'default'     => '۰۹۱۲۱۲۳۴۵۶۷',
				'label_block' => true,
				'condition'   => array( 'show_phone' => 'yes' ),
			)
		);

		$this->add_control(
			'heading_birth',
			array(
				'label'     => 'تاریخ تولد',
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_birth',
			array(
				'label'        => 'نمایش فیلد تاریخ تولد',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'label_birth',
			array(
				'label'       => 'برچسب',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'تاریخ تولد',
				'label_block' => true,
				'condition'   => array( 'show_birth' => 'yes' ),
			)
		);

		$this->add_control(
			'date_order',
			array(
				'label'     => 'ترتیب انتخاب تاریخ',
				'type'      => Controls_Manager::SELECT,
				'default'   => 'dmy',
				'options'   => array(
					'dmy' => 'روز / ماه / سال',
					'ymd' => 'سال / ماه / روز',
				),
				'condition' => array( 'show_birth' => 'yes' ),
			)
		);

		$this->add_control(
			'years_back',
			array(
				'label'     => 'چند سال گذشته در فهرست سال‌ها',
				'type'      => Controls_Manager::NUMBER,
				'min'       => 5,
				'max'       => 120,
				'step'      => 1,
				'default'   => 100,
				'condition' => array( 'show_birth' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab: consent checkbox.
	 *
	 * @return void
	 */
	private function register_consent_controls() {
		$this->start_controls_section(
			'section_consent',
			array(
				'label' => 'تأییدیه (اختیاری)',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_consent',
			array(
				'label'        => 'نمایش چک‌باکس تأییدیه',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'consent_text',
			array(
				'label'     => 'متن تأییدیه',
				'type'      => Controls_Manager::TEXTAREA,
				'rows'      => 2,
				'default'   => 'با عضویت در باشگاه مشتریان، دریافت پیامک‌های اطلاع‌رسانی را می‌پذیرم.',
				'condition' => array( 'show_consent' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab: submit button.
	 *
	 * @return void
	 */
	private function register_button_controls() {
		$this->start_controls_section(
			'section_button',
			array(
				'label' => 'دکمهٔ ثبت‌نام',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => 'متن دکمه',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'عضویت در باشگاه مشتریان',
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'button_full',
			array(
				'label'        => 'دکمه تمام‌عرض',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab: thank-you message.
	 *
	 * @return void
	 */
	private function register_thanks_controls() {
		$this->start_controls_section(
			'section_thanks',
			array(
				'label' => 'پیام تشکر و پیام‌های خطا',
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'thanks_title',
			array(
				'label'       => 'عنوان پیام تشکر',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'از عضویت شما سپاسگزاریم!',
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'thanks_message',
			array(
				'label'   => 'متن پیام تشکر',
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => 'نام شما با موفقیت در باشگاه مشتریان ثبت شد. به‌زودی پیشنهادهای ویژه را برایتان می‌فرستیم.',
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'show_thanks_image',
			array(
				'label'        => 'نمایش تصویر در پیام تشکر',
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'thanks_image',
			array(
				'label'     => 'تصویر پیام تشکر',
				'type'      => Controls_Manager::MEDIA,
				'condition' => array( 'show_thanks_image' => 'yes' ),
			)
		);

		$this->add_control(
			'again_text',
			array(
				'label'       => 'متن دکمهٔ ثبت‌نام نفر بعدی',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'ثبت‌نام یک نفر دیگر',
				'label_block' => true,
			)
		);

		$this->add_control(
			'redirect_url',
			array(
				'label'         => 'انتقال به آدرس دلخواه پس از ثبت‌نام',
				'type'          => Controls_Manager::URL,
				'placeholder'   => 'https://example.com/thanks',
				'description'   => 'اگر خالی بماند، پیام تشکر همان‌جا نمایش داده می‌شود.',
				'label_block'   => true,
				'show_external' => false,
			)
		);

		$this->add_control(
			'error_message',
			array(
				'label'       => 'پیام خطای عمومی',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'ثبت‌نام انجام نشد. لطفاً اطلاعات را بررسی کنید و دوباره تلاش کنید.',
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'duplicate_message',
			array(
				'label'       => 'پیام شمارهٔ تکراری',
				'type'        => Controls_Manager::TEXT,
				'default'     => 'این شماره تلفن قبلاً در باشگاه مشتریان ثبت شده است.',
				'label_block' => true,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: card.
	 *
	 * @return void
	 */
	private function register_card_style() {
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => 'کارت فرم',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'card_max_width',
			array(
				'label'      => 'حداکثر عرض',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 280,
						'max'  => 960,
						'step' => 10,
					),
					'%'  => array(
						'min' => 30,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 440,
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-wrap' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'card_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .mcc-card',
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .mcc-card',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .mcc-card',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => 'فاصلهٔ داخلی',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: image.
	 *
	 * @return void
	 */
	private function register_image_style() {
		$this->start_controls_section(
			'section_style_image',
			array(
				'label'     => 'تصویر',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => 'ارتفاع تصویر بالای فرم',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 60,
						'max'  => 500,
						'step' => 5,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 210,
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-is-top .mcc-media' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_side_width',
			array(
				'label'      => 'عرض تصویر کنار فرم (٪)',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min'  => 15,
						'max'  => 60,
						'step' => 1,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 38,
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-is-side .mcc-media' => 'flex: 0 0 {{SIZE}}%;',
				),
			)
		);

		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-media img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: heading.
	 *
	 * @return void
	 */
	private function register_heading_style() {
		$this->start_controls_section(
			'section_style_heading',
			array(
				'label' => 'عنوان',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => 'رنگ',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-heading' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'selector' => '{{WRAPPER}} .mcc-heading',
			)
		);

		$this->add_responsive_control(
			'heading_margin',
			array(
				'label'      => 'فاصله',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: description.
	 *
	 * @return void
	 */
	private function register_description_style() {
		$this->start_controls_section(
			'section_style_description',
			array(
				'label' => 'توضیح',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label'     => 'رنگ',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-desc'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .mcc-desc p'   => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'description_typography',
				'selector' => '{{WRAPPER}} .mcc-desc, {{WRAPPER}} .mcc-desc p',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: field labels.
	 *
	 * @return void
	 */
	private function register_label_style() {
		$this->start_controls_section(
			'section_style_labels',
			array(
				'label' => 'برچسب فیلدها',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => 'رنگ',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .mcc-label',
			)
		);

		$this->add_responsive_control(
			'label_spacing',
			array(
				'label'      => 'فاصله از فیلد',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 40,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'required_color',
			array(
				'label'     => 'رنگ ستارهٔ اجباری',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-req' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: inputs.
	 *
	 * @return void
	 */
	private function register_input_style() {
		$this->start_controls_section(
			'section_style_inputs',
			array(
				'label' => 'فیلدهای ورودی',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'selector' => '{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select',
			)
		);

		$this->start_controls_tabs( 'input_tabs' );

		$this->start_controls_tab(
			'input_tab_normal',
			array( 'label' => 'عادی' )
		);

		$this->add_control(
			'input_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_bg',
			array(
				'label'     => 'رنگ پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'input_tab_focus',
			array( 'label' => 'فوکوس' )
		);

		$this->add_control(
			'input_focus_bg',
			array(
				'label'     => 'رنگ پس‌زمینه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-input:focus, {{WRAPPER}} .mcc-select:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_focus_border',
			array(
				'label'     => 'رنگ حاشیه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-input:focus, {{WRAPPER}} .mcc-select:focus' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'input_radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_padding',
			array(
				'label'      => 'فاصلهٔ داخلی',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-input, {{WRAPPER}} .mcc-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'field_spacing',
			array(
				'label'      => 'فاصلهٔ بین فیلدها',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 60,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-field' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: button.
	 *
	 * @return void
	 */
	private function register_button_style() {
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => 'دکمه',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .mcc-btn',
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab(
			'button_tab_normal',
			array( 'label' => 'عادی' )
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-btn' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'button_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .mcc-btn',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'button_tab_hover',
			array( 'label' => 'هاور' )
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-btn:hover, {{WRAPPER}} .mcc-btn:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'button_hover_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .mcc-btn:hover, {{WRAPPER}} .mcc-btn:focus',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'button_radius',
			array(
				'label'      => 'گردی گوشه‌ها',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .mcc-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => 'فاصلهٔ داخلی',
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .mcc-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: consent.
	 *
	 * @return void
	 */
	private function register_consent_style() {
		$this->start_controls_section(
			'section_style_consent',
			array(
				'label'     => 'تأییدیه',
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_consent' => 'yes' ),
			)
		);

		$this->add_control(
			'consent_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-consent-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'consent_typography',
				'selector' => '{{WRAPPER}} .mcc-consent-text',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: success and error messages.
	 *
	 * @return void
	 */
	private function register_success_style() {
		$this->start_controls_section(
			'section_style_success',
			array(
				'label' => 'پیام تشکر و خطا',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'success_icon_color',
			array(
				'label'     => 'رنگ آیکن تأیید',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-success-icon' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'success_title_color',
			array(
				'label'     => 'رنگ عنوان تشکر',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-success-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'success_title_typography',
				'selector' => '{{WRAPPER}} .mcc-success-title',
			)
		);

		$this->add_control(
			'success_text_color',
			array(
				'label'     => 'رنگ متن تشکر',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-success-text, {{WRAPPER}} .mcc-success-text p' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'success_text_typography',
				'selector' => '{{WRAPPER}} .mcc-success-text, {{WRAPPER}} .mcc-success-text p',
			)
		);

		$this->add_control(
			'alert_color',
			array(
				'label'     => 'رنگ متن خطا',
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .mcc-alert, {{WRAPPER}} .mcc-error' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'alert_bg',
			array(
				'label'     => 'پس‌زمینهٔ کادر خطا',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .mcc-alert' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Map Elementor settings to the shared form renderer.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function to_form_args( $settings ) {
		$image = isset( $settings['image'] ) && is_array( $settings['image'] ) ? $settings['image'] : array();
		$thanks_image = isset( $settings['thanks_image'] ) && is_array( $settings['thanks_image'] ) ? $settings['thanks_image'] : array();
		$redirect = isset( $settings['redirect_url'] ) && is_array( $settings['redirect_url'] ) ? $settings['redirect_url'] : array();

		return array(
			'show_image'        => 'yes' === ( $settings['show_image'] ?? '' ),
			'image_id'          => isset( $image['id'] ) ? (int) $image['id'] : 0,
			'image_url'         => isset( $image['url'] ) ? $image['url'] : '',
			'image_position'    => $settings['image_position'] ?? 'top',
			'heading'           => $settings['heading'] ?? '',
			'heading_tag'       => $settings['heading_tag'] ?? 'h2',
			'description'       => $settings['description'] ?? '',
			'show_name'         => 'yes' === ( $settings['show_name'] ?? '' ),
			'label_name'        => $settings['label_name'] ?? '',
			'placeholder_name'  => $settings['placeholder_name'] ?? '',
			'show_phone'        => 'yes' === ( $settings['show_phone'] ?? '' ),
			'label_phone'       => $settings['label_phone'] ?? '',
			'placeholder_phone' => $settings['placeholder_phone'] ?? '',
			'show_birth'        => 'yes' === ( $settings['show_birth'] ?? '' ),
			'label_birth'       => $settings['label_birth'] ?? '',
			'date_order'        => $settings['date_order'] ?? 'dmy',
			'years_back'        => isset( $settings['years_back'] ) ? (int) $settings['years_back'] : 100,
			'required_marks'    => 'yes' === ( $settings['required_marks'] ?? '' ),
			'show_consent'      => 'yes' === ( $settings['show_consent'] ?? '' ),
			'consent_text'      => $settings['consent_text'] ?? '',
			'button_text'       => $settings['button_text'] ?? '',
			'button_full'       => 'yes' === ( $settings['button_full'] ?? '' ),
			'thanks_title'      => $settings['thanks_title'] ?? '',
			'thanks_message'    => $settings['thanks_message'] ?? '',
			'show_thanks_image' => 'yes' === ( $settings['show_thanks_image'] ?? '' ),
			'thanks_image_id'   => isset( $thanks_image['id'] ) ? (int) $thanks_image['id'] : 0,
			'thanks_image_url'  => isset( $thanks_image['url'] ) ? $thanks_image['url'] : '',
			'again_text'        => $settings['again_text'] ?? '',
			'error_message'     => $settings['error_message'] ?? '',
			'duplicate_message' => $settings['duplicate_message'] ?? '',
			'redirect_url'      => isset( $redirect['url'] ) ? $redirect['url'] : '',
		);
	}

	/**
	 * Frontend output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		echo MCC_Form::render( $this->to_form_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside the renderer.
	}
}
