<?php
/**
 * Plugin Name: Save Search
 * Description: Allows users to save searches.
 * Version: 1.0
 * Author: BM Plugin
 */

// 1.Create Database 
register_activation_hook(__FILE__, 'create_save_search_table');
function create_save_search_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'save_search';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        search_name text NOT NULL,
        search_url text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}



// 2.Search Name | Login Alert
add_action('wp_footer', 'add_save_search_script');
function add_save_search_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#save-search-btn').click(function(){
            <?php if (is_user_logged_in()): ?>
                var user_id = <?php echo get_current_user_id(); ?>;
                var search_name = prompt("Aramaya isim verin");
                var search_url = window.location.href;
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'save_search',
                        user_id: user_id,
                        search_name: search_name,
                        search_url: search_url
                    },
                    success: function(data) {
                        alert(data);
                    }
                });
            <?php else: ?>
                alert("Lütfen giriş yapınız");
            <?php endif; ?>
        });
    });
    </script>
    <?php
}



// 3.Save Database
add_action('wp_ajax_save_search', 'save_search');
function save_search() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'save_search';

    $user_id = intval($_POST['user_id']);
    $search_name = sanitize_text_field($_POST['search_name']);
    $search_url = esc_url($_POST['search_url']);
    
    $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'search_name' => $search_name,
            'search_url' => $search_url
        ),
        array(
            '%d',
            '%s',
            '%s'
        )
    );

    echo 'Arama kaydedildi.Kaydedilen aramalarınıza "Hesabım" sekmesi altından ulaşabilirsiniz.';
    wp_die();
}



// 4.My Account Tab
add_action('woocommerce_account_saved-searches_endpoint', 'display_saved_searches');
function display_saved_searches() {
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'save_search';
    
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE user_id = $user_id");

    if ($results) {
        echo '<table>';
        echo '<tr><th>Arama Adı</th><th>Arama Linki</th><th>İşlem</th></tr>';
        foreach ($results as $result) {
            echo "<tr><td>{$result->search_name}</td><td><a href='{$result->search_url}'>Aramayı Görüntüle</a></td><td><a href='#' class='rename-search' data-id='{$result->id}'>Yeniden Adlandır</a> | <a href='#' class='delete-search' data-id='{$result->id}'>Sil</a></td></tr>";
        }
        echo '</table>';
    } else {
        echo 'Hiç kayıtlı arama bulunamadı.';
    }
}

// 5.Renemae | Delete Functions
add_action('wp_ajax_rename_search', 'rename_saved_search');
function rename_saved_search() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'save_search';
    $id = $_POST['id'];
    $new_name = $_POST['new_name'];
    
    // Security $wpdb->prepare using
    $wpdb->query($wpdb->prepare("UPDATE $table_name SET search_name = %s WHERE id = %d", $new_name, $id));
    
    wp_die();
}


add_action('wp_ajax_delete_search', 'delete_saved_search');
function delete_saved_search() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'save_search';
    $id = $_POST['id'];
    
    // Security $wpdb->prepare using
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id = %d", $id));
    
    wp_die();
}



// 6.Saved Searchs Menu Item
function new_account_menu_items( $items ) {
    // Hide 'logout'
    $logout = $items['customer-logout'];
    unset( $items['customer-logout'] );

    // New menu item
    $items['saved-searches'] = __( 'Kaydedilmiş Aramalar', 'domain' );

    // 'logout' show
    $items['customer-logout'] = $logout;

    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'new_account_menu_items' );

// Add endpoint for new menu item
function add_saved_searches_endpoint() {
    add_rewrite_endpoint( 'saved-searches', EP_PAGES );
}
add_action( 'init', 'add_saved_searches_endpoint' );

// Create a custom template for the new endpoint
function display_saved_searches_content() {
    $user_id = get_current_user_id();
    
    
//    $saved_searches = array( 'Search 1', 'Search 2' );
    if ( ! empty( $saved_searches ) ) {
        foreach ( $saved_searches as $search ) {
            echo '<div>' . esc_html( $search ) . '</div>';
        }
    } else {
        echo '';
    }
}
add_action( 'woocommerce_account_saved-searches_endpoint', 'display_saved_searches_content' );

// Add title for new endpoint
function saved_searches_query_vars( $vars ) {
    $vars[] = 'saved-searches';
    return $vars;
}
add_filter( 'query_vars', 'saved_searches_query_vars', 0 );





// 7.Saved Searches Rename | Delete | Warning
add_action('wp_footer', 'add_custom_js');
function add_custom_js() {
?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.rename-search').click(function() {
            var id = $(this).data('id');
            var new_name = prompt('Yeni arama adını girin:');
            if (new_name) {
                $.post(ajaxurl, { action: 'rename_search', id: id, new_name: new_name }, function(response) {
                    location.reload();
                });
            }
        });

        $('.delete-search').click(function() {
            var id = $(this).data('id');
            if (confirm('Bu aramayı silmek istediğinizden emin misiniz?')) {
                $.post(ajaxurl, { action: 'delete_search', id: id }, function(response) {
                    location.reload();
                });
            }
        });
    });
    </script>
<?php
}



//Güncellenecek alanlar: 8 ve 9
// 8.New Product Control
function check_new_items_for_search($search_url) {
    $parsed_url = parse_url($search_url);
    parse_str($parsed_url['query'], $query_params);

    // Calculate the time for 'one hour ago'
    $daily = date('Y-m-d H:i:s', strtotime('-24 hour'));

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'date_query' => array(
            array(
                'after' => $daily // Use 'one day ago' time
            )
        ),
        'meta_query' => array()
    );

    if (isset($query_params['product_cat'])) {
        $args['product_cat'] = $query_params['product_cat'];
    }

    $query = new WP_Query($args);

    return $query->have_posts();
}




// 9.E-Mail Notifications
function check_saved_searches() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'save_search';
    $saved_searches = $wpdb->get_results("SELECT * FROM $table_name");

    foreach ($saved_searches as $search) {
        $user_id = $search->user_id;
        $search_id = $search->id;
        $search_url = $search->search_url;
        $search_name = $search->search_name;

        // Check for new products without last_checked
        if (check_new_items_for_search($search->search_url)) {
            $user_info = get_userdata($user_id);
            $to = $user_info->user_email;
            $subject = $search_name . ' için Yeni Ürünler Var';
            $message = 'Yeni ürünleri görmek için bu linki ziyaret edin: ' . $search_url;

            wp_mail($to, $subject, $message);
        }
    }
}



// WordPress cron | Saatlik kontrol | Geliştirme Test için
/*
function setup_cron_for_checking_new_items() {
    if (! wp_next_scheduled ( 'hourly_check_saved_searches' )) {
        wp_schedule_event(time(), 'hourly', 'hourly_check_saved_searches');
    }
}
add_action('wp', 'setup_cron_for_checking_new_items');

// Cron işi için aksiyon ekle
add_action('hourly_check_saved_searches', 'check_saved_searches');
*/



//10.Cron Job
function setup_cron_for_checking_new_items() {
    if (! wp_next_scheduled('daily_check_saved_searches')) {
        wp_schedule_event(time(), 'daily', 'daily_check_saved_searches');
    }
}
add_action('wp', 'setup_cron_for_checking_new_items');

add_action('daily_check_saved_searches', 'check_saved_searches');