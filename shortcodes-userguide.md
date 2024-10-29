## WooCommerce - April Gateway
#### v1.0.1

### Usage of Shortcodes

###### Available Shortcodes and Parameters
1. **april_product_bnpl_price**

    This shortcode is used to show inistallment price, based on product price (for product page), or any custom price passed as parameter.

    *Allowed Parameters*
    * **amount**
        This is the main price, based on which, the instalment price would be shown (default value to be passed: `productPrice`), if you need to pass custom price, you can pass the amount without currency symbol, like `250`
    * **color**
        This will be the color of instalment amount where it would get shown (default value to be passed: `#fa5402`)

    ![image](https://user-images.githubusercontent.com/15088401/85270651-404beb00-b497-11ea-8153-ae4455313fa7.png)

2. **april_bnpl_toggle**

     This shortcode is used to show inistallment toggle, based on total amount of products in cart, or any custom price passed as parameter. If the customer enables the toggle from here, on checkout page, under payment's section, payplan option would be selected by default.

    *Allowed Parameters*
    * **amount**
        This is the main price, based on which, the instalment price would be shown (default value to be passed: `cartAmount`), if you need to pass custom price, you can pass the amount without currency symbol, like `250`
    * **color**
        This will be the color of instalment toggle where it would get shown (default value to be passed: `#3A3CA6`)

    ![image](https://user-images.githubusercontent.com/15088401/85271343-4db5a500-b498-11ea-9246-fc014af05e53.png)

    ![image](https://user-images.githubusercontent.com/15088401/85271596-9ff6c600-b498-11ea-8ab5-9f54aeb4918a.png)

3. **april_product_bnpl_toggle**

     This shortcode is used to show inistallment toggle, ased on product price (for product page), or any custom price passed as parameter. If the customer enables the toggle from here, on checkout page, under payment's section, payplan option would be selected by default. Also, if `april_bnpl_toggle` is used on cart or any other page, the toggle for that shortcode toggle will be synced with this one.

    *Allowed Parameters*
    * **amount**
        This is the main price, based on which, the instalment price would be shown (default value to be passed: `productPrice`), if you need to pass custom price, you can pass the amount without currency symbol, like `250`
    * **price_color**
        This will be the color of instalment amount where it would get shown (default value to be passed: `#fa5402`)
    * **toggle_color**
        This will be the color of instalment toggle where it would get shown (default value to be passed: `#3A3CA6`)

    ![product-toggle](https://user-images.githubusercontent.com/15088401/85543702-ed5d6980-b637-11ea-9d48-f0d79b508cd0.png)

### Guide to Use Shortcodes in Pages

##### 1. Editable WP pages

For the editable pages, blog posts etc, the shortcode can be called easily like this

`[april_product_bnpl_price amount="productPrice" color="#fa5402"]`

`[april_bnpl_toggle amount="cartAmount" color="#3A3CA6"]`

`[april_product_bnpl_toggle amount="productPrice" price_color="#fa5402" toggle_color="#3A3CA6"]`

It can be directly used like this into the content area

![image](https://user-images.githubusercontent.com/15088401/85278425-94a89800-b4a2-11ea-83dc-a798e9d20e5c.png)

##### 2. Using WP Hooks

The shortcode can be added into an page using WordPress hooks, using `add_action()` function.

For example, to insert the **Instalment Toggle**(april_bnpl_toggle) into cart page using shortcode, you can use `woocommerce_proceed_to_checkout` hook like this

```
add_action( 'woocommerce_proceed_to_checkout', 'april_instalment_offer_on_cart', 10 );
function april_instalment_offer_on_cart() {
	print do_shortcode('[april_bnpl_toggle amount="cartAmount" color="#3A3CA6"]');
}
```

Or, to insert the **Instalment Toggle on Product page**(april_product_bnpl_toggle) using shortcode, you can use `woocommerce_single_product_summary` hook like this

```
add_action( 'woocommerce_single_product_summary', 'april_instalment_product_toggle', 10 );
function april_instalment_product_toggle() {
	print do_shortcode('[april_product_bnpl_toggle amount="productPrice" price_color="#fa5402" toggle_color="#3A3CA6"]');
}
```

To know more about this function, you may visit [https://developer.wordpress.org/reference/functions/add_action/](https://developer.wordpress.org/reference/functions/add_action/)

[Reference: https://docs.woocommerce.com/wc-apidocs](https://docs.woocommerce.com/wc-apidocs)

##### 3. PHP Template File

In any PHP file, shortcode can be used by directly calling it like this

```
<?php print do_shortcode('[april_bnpl_toggle amount="cartAmount" color="#3A3CA6"]'); ?>
```
