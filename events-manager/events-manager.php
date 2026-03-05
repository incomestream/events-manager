<?php
/*
Plugin Name: Events Manager
Description:    
Version: 1.0
Author: Dmitriy
*/

defined('ABSPATH') or die('Нет доступа!');



// custom post type
function em_register_event_post_type() {
    register_post_type('event', array(
        'labels' => array(
            'name' => 'События',
            'singular_name' => 'Событие',
            'add_new' => 'Добавить событие',
            'add_new_item' => 'Добавить новое событие',
            'edit_item' => 'Редактировать событие',
            'new_item' => 'Новое событие',
            'view_item' => 'Просмотр события',
            'search_items' => 'Искать события',
            'not_found' => 'Событий не найдено',
            'not_found_in_trash' => 'В корзине событий не найдено',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'show_in_rest' => false,
        'rewrite' => array('slug' => 'events'),
    ));
}
add_action('init', 'em_register_event_post_type');


function em_add_meta_boxes() {
    add_meta_box(
        'em_event_meta',
        'Детали события',
        'em_render_meta_box',
        'event',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'em_add_meta_boxes');

function em_render_meta_box($post) {
    $event_date = get_post_meta($post->ID, 'event_date', true);
    $event_place = get_post_meta($post->ID, 'event_place', true);

    wp_nonce_field('em_save_meta_box', 'em_meta_box_nonce');

    ?>
    <p>
        <label for="em_event_date">Дата события (гггг-мм-дд):</label><br>
        <input type="date" id="em_event_date" name="em_event_date" value="<?php echo esc_attr($event_date); ?>" style="width:100%;">
    </p>
    <p>
        <label for="em_event_place">Место проведения:</label><br>
        <input type="text" id="em_event_place" name="em_event_place" value="<?php echo esc_attr($event_place); ?>" style="width:100%;">
    </p>
    <?php
}

function em_save_post($post_id) {
    if (!isset($_POST['em_meta_box_nonce']) || !wp_verify_nonce($_POST['em_meta_box_nonce'], 'em_save_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;


    if (isset($_POST['em_event_date'])) {
        $date = sanitize_text_field($_POST['em_event_date']);
        update_post_meta($post_id, 'event_date', $date);
    }


    if (isset($_POST['em_event_place'])) {
        $place = sanitize_text_field($_POST['em_event_place']);
        update_post_meta($post_id, 'event_place', $place);
    }
}
add_action('save_post', 'em_save_post');


// shortcode

function em_events_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'posts_per_page' => 3,
        'offset' => 0,
    ), $atts, 'events_list');

    $today = current_time('Y-m-d');

    $args = array(
        'post_type' => 'event',
        'posts_per_page' => intval($atts['posts_per_page']),
        'offset' => intval($atts['offset']),
        'meta_key' => 'event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'event_date',
                'compare' => '>=',
                'value' => $today,
                'type' => 'DATE'
            )
        ),
    );

    $query = new WP_Query($args);
    $output = '<div class="events-list">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $event_date = get_post_meta(get_the_ID(), 'event_date', true);
            $event_place = get_post_meta(get_the_ID(), 'event_place', true);
            $formatted_date = date('d.m.Y', strtotime($event_date));
            $output .= '<div class="event-item">';
            $output .= '<h3 class="event-title">' . get_the_title() . '</h3>';
            $output .= '<div class="event-date">Дата: ' . esc_html($formatted_date) . '</div>';
            $output .= '<div class="event-place">Место: ' . esc_html($event_place) . '</div>';
            $output .= '</div>';
        }
        wp_reset_postdata();
    } else {
        $output .= 'Нет предстоящих событий.';
    }

    $output .= '<button id="load-more-events" data-offset="' . intval($atts['posts_per_page']) . '">Показать больше</button>';

    $output .= '<div id="additional-events"></div>';

    $output .= '</div>';

    return $output;
}
add_shortcode('events_list', 'em_events_list_shortcode');


// register scripts

function em_enqueue_scripts() {
    wp_enqueue_style('em-style', plugins_url('css/em-style.css', __FILE__));
    wp_enqueue_script('em-script', plugins_url('js/em-script.js', __FILE__), array('jquery'), null, true);

    wp_localize_script('em-script', 'em_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('em_load_more_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'em_enqueue_scripts');


function em_load_more_events() {

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'em_load_more_nonce')) {
        wp_send_json_error('Недействительный запрос', 403);
        wp_die();
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $posts_per_page = 3;
    $today = current_time('Y-m-d');

    $args = array(
        'post_type' => 'event',
        'posts_per_page' => $posts_per_page,
        'offset' => $offset,
        'meta_key' => 'event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'event_date',
                'compare' => '>=',
                'value' => $today,
                'type' => 'DATE'
            )
        ),
    );

    $query = new WP_Query($args);
    $results = '';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $event_date = get_post_meta(get_the_ID(), 'event_date', true);
            $event_place = get_post_meta(get_the_ID(), 'event_place', true);
            $formatted_date = date('d.m.Y', strtotime($event_date));
            $results .= '<div class="event-item">';
            $results .= '<h3 class="event-title">' . get_the_title() . '</h3>';
            $results .= '<div class="event-date">Дата: ' . esc_html($formatted_date) . '</div>';
            $results .= '<div class="event-place">Место: ' . esc_html($event_place) . '</div>';
            $results .= '</div>';
        }
        wp_reset_postdata();

        $new_offset = $offset + $posts_per_page;
        wp_send_json_success(array(
            'html' => $results,
            'new_offset' => $new_offset
        ));
    } else {
        wp_send_json_success(array(
            'html' => 'Больше событий нет.',
            'new_offset' => $offset
        ));
    }
    wp_die();
}
add_action('wp_ajax_em_load_more', 'em_load_more_events');
add_action('wp_ajax_nopriv_em_load_more', 'em_load_more_events');
?>