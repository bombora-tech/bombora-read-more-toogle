<?php
/**
 * Plugin Name:       Bombora Read More Toggle
 * Plugin URI:        https://github.com/bombora-tech/bombora-read-more-toogle
 * Description:       Tag [read]...[/read] skryje obsažený text za tlačítko „Pokračovat“ (bez reloadu stránky). Funguje v obsahu příspěvků/produktů, krátkém popisu produktu i v popisu kategorií/taxonomií. Texty tlačítek jsou přeložené (CS, SK, DE, FR, NL, EN, RU) a lze je přepsat ve WPML String Translation.
 * Version:           1.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Bombora group s.r.o.
 * Author URI:        https://www.zgruzie.cz/
 * License:           GPL-2.0-or-later
 * Text Domain:       bombora-read-more-toggle
 * Domain Path:       /languages
 * Update URI:        https://github.com/bombora-tech/bombora-read-more-toogle
 *
 * @package Bombora\ReadMoreToggle
 */

defined( 'ABSPATH' ) || exit;

// Pojistka: druhá kopie, nebo stará verze (složka read-more-toggle, třída RMT_Read_More_Toggle) už běží.
// Třída má jiný název než ve staré verzi, aby ji kontrola class_exists() nenašla dřív, než se sama deklaruje.
if ( defined( 'BOMBORA_RMT_VERSION' ) || class_exists( 'RMT_Read_More_Toggle', false ) ) {
	return;
}

define( 'BOMBORA_RMT_VERSION', '1.1.1' );

final class Bombora_Read_More_Toggle {

	private const WPML_CONTEXT = 'Read More Toggle';

	private static ?Bombora_Read_More_Toggle $instance = null;
	private int $counter     = 0;
	private bool $used       = false;

	public static function instance(): Bombora_Read_More_Toggle {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'read', array( $this, 'render_shortcode' ) );

		// WordPress ve výchozím stavu nezpracovává shortcody v popisu termů (kategorie/taxonomie).
		add_filter( 'term_description', 'do_shortcode' );

		// Krátký popis produktu ve WooCommerce.
		add_filter( 'woocommerce_short_description', 'do_shortcode', 11 );

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// Registrace řetězců do WPML String Translation (původní hodnoty beze změny kvůli existujícím překladům).
		add_action( 'init', array( $this, 'register_wpml_strings' ) );

		// CSS vždy do <head> (aby se text stihl schovat dřív, než prohlížeč vykreslí obsah).
		add_action( 'wp_head', array( $this, 'print_head_css' ) );

		// JS jednou v patičce, pokud se na stránce shortcode skutečně použil.
		add_action( 'wp_footer', array( $this, 'print_footer_script' ), 99 );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'bombora-read-more-toggle', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function register_wpml_strings(): void {
		if ( has_action( 'wpml_register_single_string' ) ) {
			do_action( 'wpml_register_single_string', self::WPML_CONTEXT, 'Continue reading button', $this->default_show_text() );
			do_action( 'wpml_register_single_string', self::WPML_CONTEXT, 'Hide button', $this->default_hide_text() );
		}
	}

	private function default_show_text(): string {
		return (string) apply_filters( 'rmt_default_show_text', 'Pokračovat' );
	}

	private function default_hide_text(): string {
		return (string) apply_filters( 'rmt_default_hide_text', 'Skrýt' );
	}

	/**
	 * Pořadí: překlad z WPML String Translation → vestavěný překlad podle jazyka → výchozí text.
	 */
	private function translate( string $default, string $name, string $builtin ): string {
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$translated = (string) apply_filters( 'wpml_translate_single_string', $default, self::WPML_CONTEXT, $name );
			if ( '' !== $translated && $translated !== $default ) {
				return $translated;
			}
		}
		return '' !== $builtin ? $builtin : $default;
	}

	private function show_text(): string {
		$default = $this->default_show_text();
		$builtin = 'Pokračovat' === $default ? __( 'Pokračovat', 'bombora-read-more-toggle' ) : $default;
		return $this->translate( $default, 'Continue reading button', $builtin );
	}

	private function hide_text(): string {
		$default = $this->default_hide_text();
		$builtin = 'Skrýt' === $default ? __( 'Skrýt', 'bombora-read-more-toggle' ) : $default;
		return $this->translate( $default, 'Hide button', $builtin );
	}

	/**
	 * @param array<string,string>|string $atts    Atributy (nepoužívají se).
	 * @param string|null                 $content Skrytý obsah.
	 */
	public function render_shortcode( $atts, $content = '' ): string {
		if ( '' === trim( (string) $content ) ) {
			return '';
		}

		++$this->counter;
		$post_id   = (int) get_the_ID();
		$id        = 'rmt-hidden-' . $post_id . '-' . $this->counter . '-' . wp_rand( 1000, 9999 );
		$show_text = $this->show_text();
		$hide_text = $this->hide_text();

		// Obsah může sám obsahovat další shortcody.
		$content = do_shortcode( (string) $content );

		// Blokové prvky (odstavce, nadpisy, seznamy…) potřebují obálku <div>, jinak <span> – tlačítko drží na řádku.
		$is_block = (bool) preg_match( '/<(p|div|h[1-6]|ul|ol|li|table|blockquote|pre|section|article|figure)\b/i', $content );
		$tag      = $is_block ? 'div' : 'span';
		$modifier = $is_block ? 'rmt-block' : 'rmt-inline';

		// U blokového obsahu jde tlačítko PŘED skrytý <div> (div nemůže být uvnitř běžícího <p>).
		$button_html = sprintf(
			'<a href="#%1$s" class="rmt-toggle-btn" role="button" aria-controls="%1$s" data-target="%1$s" data-show-text="%2$s" data-hide-text="%3$s" aria-expanded="false">%4$s</a>',
			esc_attr( $id ),
			esc_attr( $show_text ),
			esc_attr( $hide_text ),
			esc_html( $show_text )
		);
		$hidden_html = sprintf(
			'<%1$s class="rmt-hidden-content %2$s" id="%3$s">%4$s</%1$s>',
			$tag,
			esc_attr( $modifier ),
			esc_attr( $id ),
			$content
		);

		$this->used = true;

		return $is_block ? $button_html . $hidden_html : $hidden_html . $button_html;
	}

	public function print_head_css(): void {
		echo '<style id="rmt-css">' . $this->inline_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- statické CSS.
	}

	public function print_footer_script(): void {
		if ( ! $this->used ) {
			return;
		}
		echo '<script id="rmt-js">' . $this->inline_js() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- statický JS.
	}

	private function inline_js(): string {
		return <<<'JS'
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.rmt-toggle-btn');if(!b)return;e.preventDefault();var t=document.getElementById(b.getAttribute('data-target'));if(!t)return;var o=t.classList.toggle('rmt-open');b.setAttribute('aria-expanded',o?'true':'false');b.textContent=o?b.getAttribute('data-hide-text'):b.getAttribute('data-show-text');});
JS;
	}

	private function inline_css(): string {
		return '.rmt-hidden-content{display:none;max-width:100%;overflow-wrap:break-word;word-break:break-word}'
			. '.rmt-hidden-content.rmt-inline.rmt-open{display:inline}'
			. '.rmt-hidden-content.rmt-block.rmt-open{display:block}'
			. '.rmt-toggle-btn{display:inline;margin-left:4px;color:inherit;text-decoration:underline;cursor:pointer;font-size:inherit}'
			. '.rmt-toggle-btn:hover{opacity:.75}';
	}
}

Bombora_Read_More_Toggle::instance();
