<?php
/**
*
* @wordpress-plugin
* Plugin Name:       User Role Prices Plugin
* Plugin URI:        http://wptestdemo.com/
* Description:       Give user privilege to add different prices for products based on user roles.
* Version:           1.0.0
* Author:            Saswati Sagarika
* Author URI:        http://wptestdemo.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}        

//check if session is present or not
if ( !session_id() ) {
    //start a session
    session_start();

}

class UserRolePrices {

    /**
     * Registering the actions
     */
    function __construct() {

        add_action( 'woocommerce_product_options_general_product_data', 
            array( $this, 'woo_add_custom_general_fields' ) );
        add_action( 'woocommerce_variation_options_pricing', 
            array( $this, 'woo_add_custom_field_to_variations' ), 10, 3  );
        add_action( 'woocommerce_process_product_meta', 
            array( $this,  'woo_save_custom_general_fields' ), 10, 1 );
        add_action( 'woocommerce_save_product_variation', 
            array( $this,  'woo_save_custom_field_variations' ), 10, 2 );
        add_filter( 'woocommerce_get_price_html', 
            array( $this, 'woo_change_product_price_html' ), 10, 2 );    
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_custom_price' ) );
        add_action( 'admin_notices', array( $this, 'woo_notices' ) );

    }


    /**
     * Activation.
     */
    public function activation() {

        flush_rewrite_rules();
    }

    /**
     * Deactivation.
     */
    public function deactivation() {

        flush_rewrite_rules();
    }

    /**
     * Replace space with '_' 
     *
     * @param $term
     */
    public function trimrole( $term ) {

        return  str_replace( ' ', '_', $term );
    }

    /**
    * Get the product price for  current user role
    *
    * @param $product_id
    */
    public function woo_get_role_based_product( $product_id ) {

        global $current_user;

        $user_roles = $current_user->roles;
        $user_role = array_shift( $user_roles );

        //replace extra space by '_' in roles
        $role = ucwords( $this->trimrole( $user_role ) );

        //return the product value
        return get_post_meta(  $product_id , $role . '_price_value', true );
    }

    /**
    * Update the price based on role
    *
    * @param $product_id, $role, $price
    */
    public function woo_update_price_role_based_product( $product_id, $role, $price ) {
        //check if the $price is in monetary decimal format
        if ( preg_match("/^[0-9]+(.[0-9]{2})?$/", $price ) == false ) {
            if ( !empty( $price ) ) {
                //set the error message if any thing other than monetary decimal format and null
                $_SESSION['woo_error_notices'] .= '<div class="error"><p>'.$role.' Price field has invalid data entry. Please enter in monetary decimal(.) format without thousand separators and currency symbol.</p></div>';
            }
            
        } else {

            update_post_meta( $product_id, $role.'_price_value', esc_attr( $price  ) );
        }

        return get_post_meta(  $product_id , $role.'_price_value', true );
    }

     /**
     * Print error messages if any present
     *
     */
    public function woo_notices() {

        //check and print if any error notices are present
        if ( !empty ( $_SESSION['woo_error_notices'] ) ) print  $_SESSION['woo_error_notices'];

        //unset the session variable
        unset ( $_SESSION['woo_error_notices'] );
    }

     /**
     * Add price fields for the user roles in general tab
     *
     */
    public function woo_add_custom_general_fields() {
        
        //get the wordpress user role list
        global $wp_roles;
        $roles = $wp_roles->get_names();

        foreach ( $roles as $role ) {
            //replace space with '_' in role name
            $trimrole = $this->trimrole( $role );

            //create text field in the general tab
            woocommerce_wp_text_input( 
                array( 
                    'id'          => $trimrole.'_price_value', 
                    'label'       => __( $role.' Price', 'woocommerce' ), 
                    'desc_tip'    => 'true',
                )
            );
       }
        
    }

     /**
     * Add price fields for the user roles in variation tab
     *
     * @param $loop, $variation_data, $variation
     */
    public function woo_add_custom_field_to_variations( $loop, $variation_data, $variation ) {

        //get the wordpress user role list
        global $wp_roles;
        $roles = $wp_roles->get_names();

        foreach ( $roles as $role ) {
            //replace space with '_' in role name
            $trimrole = $this->trimrole( $role );

            //create text field for variable products present in variation tab tab
            woocommerce_wp_text_input( 
                array(
                    'id' => $trimrole.'_price_value[' . $loop . ']',
                    'class' => 'short',
                    'label' => __( $role.' Price', 'woocommerce' ),
                    'value' => get_post_meta( $variation->ID, $trimrole.'_price_value', true )
                )
            );
        }
    }

    /**
     * Save the price data for differnt user roles from general tab
     *
     * @param $post_id
     */
    public function woo_save_custom_general_fields( $post_id ) {
        
        //get the list of wordpress roles
        global $wp_roles;
        $roles = $wp_roles->get_names();

        foreach ( $roles as $role ) {

            $role = $this->trimrole( $role );
            $woocommerce_general_price = $_POST[$role.'_price_value'];

            //update the price based on $role
            $this->woo_update_price_role_based_product( $post_id, $role, $woocommerce_general_price );
    
        }

    }

    /**
     * Save the price data for differnt user roles from variation tab
     *
     * @param $variation_id, $i
     */
    public function woo_save_custom_field_variations( $variation_id, $i ) {

        //get the list of wordpress roles
        global $wp_roles;
        $roles = $wp_roles->get_names();

        foreach ( $roles as $role ) {

            $role = $this->trimrole( $role );
            $woocommerce_var_price = $_POST[$role.'_price_value'][$i];
            //update the price based on $role
            $this->woo_update_price_role_based_product( $variation_id, $role, $woocommerce_var_price );

        }
    }

     /**
     * Change the price in archive and single page
     *
     * @param $price_html, $product
     */
    public function woo_change_product_price_html( $price_html, $product ) {
       
        $price = $this->woo_get_role_based_product( $product->id );

        if ( $price ) {
            $newprice_html = '<span class="amount">'.wc_price( $price ).' per Unit</span>';
            return $newprice_html;
        }
       
        return $price_html.' per Unit';
    }
    
    /**
     * Change the price in cart page
     *
     * @param $price, $cart_item, $cart_item_key 
     */
    public function woo_change_product_price_cart( $price, $cart_item, $cart_item_key ) {
        
        //get the new price based on user role
        $newprice = $this->woo_get_role_based_product( $cart_item['product_id'] );

        //check if the new price is null or not
        if ( $newprice ) {
            $price = wc_price ( $newprice ).' per Unit';
            return $price;
        }

        return $price.' per Unit';
    }

    /**
     * Change the price in cart object on before caluculation hook
     *
     * @param $cart_object
     */
    public function add_custom_price( $cart_object ) {

        //check if we are on cart page or checkout page or anyother page
        if ( is_cart() || is_checkout() ) {
            
            foreach ( $cart_object->cart_contents as $key => $value ) {

                //get the new price based on user role
                $newprice = $this->woo_get_role_based_product( $value['product_id'] );
               
                //check if the new price is null or not
                if ( $newprice ) {
                    //set the price as new price
                    $value['data']->set_price( $newprice );
                }
            }
        }
    }
}

if ( class_exists( 'UserRolePrices' ) ) {

    $userRolePrices = new UserRolePrices();
}

register_activation_hook( __FILE__, array( $userRolePrices, 'activation' ) );
register_deactivation_hook( __FILE__, array( $userRolePrices, 'deactivation' ) );