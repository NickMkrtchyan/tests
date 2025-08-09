<?php 

add_action( 'tutor_course/single/before/sidebar', 'custom_text_before_tutor_sidebar' );

function custom_text_before_tutor_sidebar() {
    echo '<div class="custom-tutor-message tutor-mb-24" style="padding:15px; background:#f3f3f3; border:1px solid #ddd; border-radius:5px;font-size: 14px;">';
    echo '<strong>❗ Ուշադրություն:</strong> <br>Դասընթացը համարվում է թվային/առցանց ծառայություն։ Ըստ մեր քաղաքականության՝ վճարումից հետո և ծառայությանը մուտք ստանալուց հետո <strong>գումարի վերադարձ չի իրականացվում!';
    echo '</div>';
}

// add_action( 'user_register', 'myplugin_registration_save', 10, 1 );
// function myplugin_registration_save( $user_id ) {
// return tutor_utils()->do_enroll(1291, 0, $user_id);
// }


add_action( 'woocommerce_order_status_completed', 'rankup_auto_enroll_on_course_purchase' );

function rankup_auto_enroll_on_course_purchase( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return;
    }

    // Продукты, покупка которых должна активировать автоматическую запись
    $trigger_product_ids = array( 2145 );

    $should_enroll = false;

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();

        if ( in_array( $product_id, $trigger_product_ids ) ) {
            $should_enroll = true;
            break;
        }
    }

    if ( $should_enroll ) {
        $course_id_to_enroll = 1291;

        // Проверка, не записан ли уже пользователь на курс
        $is_enrolled = tutor_utils()->is_enrolled( $course_id_to_enroll, $user_id );

        if ( ! $is_enrolled ) {
            tutor_utils()->do_enroll( $course_id_to_enroll, 0, $user_id );
        }
    }
}
