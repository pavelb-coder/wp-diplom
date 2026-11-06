<?php
/**
 * Проверка изоляции имён.
 *
 * Плагин WP Olympiad Design (github.com/pavelb-coder/wp-design) занимает
 * префиксы WPD_ / wpd_ / _wpd_. Совпадение давало фатальную ошибку
 * «Cannot declare class WPD_Renderer, because the name is already in use».
 *
 * Этот тест падает, если в коде появится хоть одно имя из чужого пространства.
 *
 * @package WP_Diplom
 */

define( 'WPDC_TEST_ROOT', __DIR__ . '/../' );

$pass = 0;
$fail = 0;

/**
 * Отмечает результат проверки.
 *
 * @param bool   $ok      Условие.
 * @param string $message Описание.
 */
function check( $ok, $message ) {
	global $pass, $fail;

	if ( $ok ) {
		++$pass;
		echo '✓ ' . $message . "\n";
		return;
	}

	++$fail;
	echo '✗ ' . $message . "\n";
}

$root = realpath( WPDC_TEST_ROOT );

/**
 * Собирает файлы плагина.
 *
 * @param string $root Корень.
 * @return string[]
 */
function plugin_files( $root ) {
	$files    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$path = $file->getPathname();

		if ( false !== strpos( $path, '/.git/' ) ) {
			continue;
		}

		if ( in_array( $file->getExtension(), array( 'php', 'css', 'js' ), true ) ) {
			$files[] = $path;
		}
	}

	sort( $files );

	return $files;
}

$files = plugin_files( $root );

check( count( $files ) > 15, 'файлы плагина найдены: ' . count( $files ) );

// --- 1. Чужие глобальные имена ------------------------------------------
// \b перед шаблоном: wp-diplom, WPD-2026 и прочие слагы не считаются.
$forbidden = array(
	'класс или константа WPD_*' => '/\bWPD_[A-Z]/',
	'мета-ключ _wpd_*'          => '/\b_wpd_/',
	'функция или опция wpd_*'   => '/\bwpd_/',
	'CSS-класс или хэндл wpd-*' => '/\bwpd-/',
);

foreach ( $forbidden as $label => $pattern ) {
	$hits = array();

	foreach ( $files as $file ) {
		$content = (string) file_get_contents( $file );

		if ( preg_match_all( $pattern, $content, $m ) ) {
			$hits[ substr( $file, strlen( $root ) + 1 ) ] = count( $m[0] );
		}
	}

	// Исключение: тесты сами содержат эти шаблоны в описании проверки.
	foreach ( array_keys( $hits ) as $relative ) {
		if ( 0 === strpos( $relative, 'tests/conflict-test.php' ) ) {
			unset( $hits[ $relative ] );
		}
	}

	check( empty( $hits ), 'нет чужих имён: ' . $label . ( $hits ? ' — найдено в ' . implode( ', ', array_keys( $hits ) ) : '' ) );
}

// --- 1.5. Ни одного «хвоста» старого префикса ---------------------------
// Именно так проскочили хуки вида save_post_wpd_template: слева от wpd_ стоял
// символ слова, и проверка по границе слова их не видела.
$tails = array();

foreach ( $files as $file ) {
	if ( 'conflict-test.php' === basename( $file ) ) {
		continue;
	}

	$content = (string) file_get_contents( $file );

	if ( preg_match_all( '/[A-Za-z0-9_]wpd_[a-z]/', $content, $m ) ) {
		$tails[ substr( $file, strlen( $root ) + 1 ) ] = array_unique( $m[0] );
	}
}

check( empty( $tails ), 'нет имён вида …wpd_… (хуки, фильтры, опции)' . ( $tails ? ': ' . wp_json_encode( $tails ) : '' ) );

// --- 2. Свой префикс действительно используется --------------------------
$main = (string) file_get_contents( $root . '/wp-diplom.php' );

check( false !== strpos( $main, "define( 'WPDC_VERSION'" ) || false !== strpos( $main, 'WPDC_VERSION' ), 'префикс WPDC_ используется' );
check( false !== strpos( $main, "class_exists( 'WPDC_Plugin' )" ), 'есть защита от второй копии плагина' );
check( false !== strpos( $main, "if ( ! defined( 'WPDC_DIR' ) )" ), 'константы объявляются через defined()' );

// --- 3. Переименованные данные: нет старых слагов ------------------------
$old_slugs = array( "'wpd_template'", "'wpd_certificate'", "'wpd_settings'", "'wpd_program'" );
$leftovers = array();

foreach ( $files as $file ) {
	if ( 'conflict-test.php' === basename( $file ) ) {
		continue; // Здесь старые слаги перечислены намеренно.
	}

	$content = (string) file_get_contents( $file );

	foreach ( $old_slugs as $slug ) {
		if ( false !== strpos( $content, $slug ) ) {
			$leftovers[] = substr( $file, strlen( $root ) + 1 ) . ' → ' . $slug;
		}
	}
}

check( empty( $leftovers ), 'старые слаги не используются' . ( $leftovers ? ': ' . implode( '; ', $leftovers ) : '' ) );

// --- 4. Текст домена и слаг плагина не тронуты ---------------------------
check( false !== strpos( $main, 'Text Domain:       wp-diplom' ), 'текстовый домен wp-diplom сохранён' );
check( file_exists( $root . '/wp-diplom.php' ), 'главный файл wp-diplom.php на месте (слаг плагина не менялся)' );

// --- 5. Хуки ссылаются на реальные типы записей плагина -----------------
$admin_file = (string) file_get_contents( $root . '/includes/class-wpdc-admin.php' );

preg_match_all( "/'(save_post_[a-z_]+|manage_[a-z_]+_posts_columns)'/", $admin_file, $hooks );

$bad_hooks = array();

foreach ( array_unique( $hooks[1] ) as $hook ) {
	if ( ! preg_match( '/wpdc_(certificate|template)/', $hook ) ) {
		$bad_hooks[] = $hook;
	}
}

check( empty( $bad_hooks ), 'все хуки указывают на wpdc_certificate / wpdc_template' . ( $bad_hooks ? ': ' . implode( ', ', $bad_hooks ) : '' ) );
check( count( $hooks[1] ) >= 4, 'хуки сохранения и колонок найдены: ' . count( array_unique( $hooks[1] ) ) );

echo "\n";
echo "Пройдено: {$pass}, провалено: {$fail}\n";

exit( 0 === $fail ? 0 : 1 );
