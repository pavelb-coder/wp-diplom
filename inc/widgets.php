<?php
/**
 * Кастомный виджет «Последние проекты».
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Виджет со списком проектов.
 */
class WP_Diplom_Recent_Projects_Widget extends WP_Widget {

	/**
	 * Конструктор.
	 */
	public function __construct() {
		parent::__construct(
			'wp_diplom_recent_projects',
			esc_html__( 'Последние проекты', 'wp-diplom' ),
			array(
				'description'                 => esc_html__( 'Список последних записей типа «Проект».', 'wp-diplom' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Фронтенд.
	 *
	 * @param array $args     Аргументы сайдбара.
	 * @param array $instance Настройки виджета.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Последние проекты', 'wp-diplom' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		$query = new WP_Query( array(
			'post_type'           => 'project',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );

		echo wp_kses_post( $args['before_widget'] );
		echo wp_kses_post( $args['before_title'] ) . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . wp_kses_post( $args['after_title'] );

		if ( $query->have_posts() ) {
			echo '<ul class="widget-projects">';
			while ( $query->have_posts() ) {
				$query->the_post();
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( get_permalink() ),
					esc_html( get_the_title() )
				);
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'Проектов пока нет.', 'wp-diplom' ) . '</p>';
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Форма настроек.
	 *
	 * @param array $instance Текущие настройки.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : esc_html__( 'Последние проекты', 'wp-diplom' );
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Заголовок:', 'wp-diplom' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Количество:', 'wp-diplom' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" step="1" min="1"
				value="<?php echo esc_attr( $count ); ?>">
		</p>
		<?php
	}

	/**
	 * Сохранение настроек.
	 *
	 * @param array $new_instance Новые значения.
	 * @param array $old_instance Старые значения.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['count'] = absint( $new_instance['count'] ?? 5 );

		return $instance;
	}
}

/**
 * Регистрация виджета.
 */
function wp_diplom_register_widgets() {
	register_widget( 'WP_Diplom_Recent_Projects_Widget' );
}
add_action( 'widgets_init', 'wp_diplom_register_widgets' );
