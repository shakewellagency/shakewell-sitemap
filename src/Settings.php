<?php
/**
 * Settings: stored option, constants and filter, plus the settings screen.
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the effective settings and renders the settings screen.
 *
 * Precedence, lowest to highest: defaults, the saved option, a SHAKEWELL_SITEMAP_<KEY> constant,
 * then the shakewell_sitemap_settings filter.
 */
final class Settings {

	public const OPTION = 'shakewell_sitemap';

	public const DEFAULTS = array(
		'post_types'     => array( 'post', 'page' ),
		'taxonomies'     => array(),
		'ignore'         => '',
		'public_url'     => '',
		'per_page'       => 1000,
		'exclude_hidden' => true,
		'disable_core'   => true,
	);

	/**
	 * Returns the effective settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		$s     = array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : array() );

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$constant = 'SHAKEWELL_SITEMAP_' . strtoupper( $key );
			if ( defined( $constant ) ) {
				$s[ $key ] = constant( $constant );
			}
		}

		/**
		 * Filters the effective settings array (keys as in Settings::DEFAULTS).
		 *
		 * @param array $s Settings.
		 */
		$s = apply_filters( 'shakewell_sitemap_settings', $s );

		$s['post_types']     = array_values( array_filter( (array) $s['post_types'], 'is_string' ) );
		$s['taxonomies']     = array_values( array_filter( (array) $s['taxonomies'], 'is_string' ) );
		$s['ignore']         = is_array( $s['ignore'] ) ? implode( ',', $s['ignore'] ) : (string) $s['ignore'];
		$s['public_url']     = untrailingslashit( (string) $s['public_url'] );
		$s['per_page']       = max( 0, min( 50000, (int) $s['per_page'] ) );
		$s['exclude_hidden'] = (bool) $s['exclude_hidden'];
		$s['disable_core']   = (bool) $s['disable_core'];

		return $s;
	}

	/**
	 * Registers the option and the settings screen.
	 */
	public static function register(): void {
		register_setting(
			'shakewell_sitemap',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
		add_options_page( __( 'Sitemap', 'shakewell-sitemap' ), __( 'Sitemap', 'shakewell-sitemap' ), 'manage_options', 'shakewell-sitemap', array( self::class, 'render' ) );
	}

	/**
	 * Cleans submitted settings. Unchecked boxes arrive as missing keys.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$types = array_keys( get_post_types( array( 'public' => true ) ) );
		$taxes = array_keys( get_taxonomies( array( 'public' => true ) ) );

		return array(
			'post_types'     => array_values( array_intersect( (array) ( $input['post_types'] ?? array() ), $types ) ),
			'taxonomies'     => array_values( array_intersect( (array) ( $input['taxonomies'] ?? array() ), $taxes ) ),
			'ignore'         => sanitize_text_field( (string) ( $input['ignore'] ?? '' ) ),
			'public_url'     => untrailingslashit( esc_url_raw( (string) ( $input['public_url'] ?? '' ) ) ),
			'per_page'       => min( 50000, absint( $input['per_page'] ?? self::DEFAULTS['per_page'] ) ),
			'exclude_hidden' => ! empty( $input['exclude_hidden'] ),
			'disable_core'   => ! empty( $input['disable_core'] ),
		);
	}

	/**
	 * Prints the settings screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shakewell Sitemap', 'shakewell-sitemap' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: sitemap URL. */
					esc_html__( 'Your sitemap is at %s. Settings set by a constant or a filter override this screen.', 'shakewell-sitemap' ),
					'<a href="' . esc_url( home_url( 'sitemap.xml' ) ) . '">' . esc_html( home_url( 'sitemap.xml' ) ) . '</a>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php settings_fields( 'shakewell_sitemap' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'shakewell-sitemap' ); ?></th>
						<td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'shakewell-sitemap' ); ?></legend>
							<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $object ) : ?>
								<label><input type="checkbox" name="shakewell_sitemap[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $s['post_types'], true ) ); ?>> <?php echo esc_html( $object->labels->name ); ?></label><br>
							<?php endforeach; ?>
						</fieldset></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxonomies', 'shakewell-sitemap' ); ?></th>
						<td><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Taxonomies', 'shakewell-sitemap' ); ?></legend>
							<?php foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $slug => $object ) : ?>
								<label><input type="checkbox" name="shakewell_sitemap[taxonomies][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $s['taxonomies'], true ) ); ?>> <?php echo esc_html( $object->labels->name ); ?></label><br>
							<?php endforeach; ?>
						</fieldset></td>
					</tr>
					<tr>
						<th scope="row"><label for="ssm-ignore"><?php esc_html_e( 'Ignored URL strings', 'shakewell-sitemap' ); ?></label></th>
						<td><input id="ssm-ignore" class="regular-text" type="text" name="shakewell_sitemap[ignore]" value="<?php echo esc_attr( $s['ignore'] ); ?>">
							<p class="description"><?php esc_html_e( 'Comma separated, no spaces after the commas. Any URL containing one of them is left out.', 'shakewell-sitemap' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ssm-public"><?php esc_html_e( 'Public site URL', 'shakewell-sitemap' ); ?></label></th>
						<td><input id="ssm-public" class="regular-text" type="url" name="shakewell_sitemap[public_url]" value="<?php echo esc_attr( $s['public_url'] ); ?>" placeholder="https://www.example.com">
							<p class="description"><?php esc_html_e( 'Headless sites: the address visitors use. It replaces the WordPress address in every URL. Leave empty on a normal site.', 'shakewell-sitemap' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ssm-per-page"><?php esc_html_e( 'Entries per file', 'shakewell-sitemap' ); ?></label></th>
						<td><input id="ssm-per-page" type="number" min="0" max="50000" name="shakewell_sitemap[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>">
							<p class="description"><?php esc_html_e( 'Up to 50000. 0 puts everything of a type in one file.', 'shakewell-sitemap' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hidden content', 'shakewell-sitemap' ); ?></th>
						<td><label><input type="checkbox" name="shakewell_sitemap[exclude_hidden]" value="1" <?php checked( $s['exclude_hidden'] ); ?>> <?php esc_html_e( 'Leave out password protected and noindex content', 'shakewell-sitemap' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WordPress sitemap', 'shakewell-sitemap' ); ?></th>
						<td><label><input type="checkbox" name="shakewell_sitemap[disable_core]" value="1" <?php checked( $s['disable_core'] ); ?>> <?php esc_html_e( 'Turn off the built-in wp-sitemap.xml', 'shakewell-sitemap' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
