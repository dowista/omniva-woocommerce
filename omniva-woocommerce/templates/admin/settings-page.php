<?php
if ( ! defined('ABSPATH') ) {
  exit;
}
?>
<div class="wrap woocommerce">
  <h1><?php esc_html_e('Omniva settings', 'omnivalt'); ?></h1>

  <?php WC_Admin_Settings::show_messages(); ?>

  <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=omnivalt-settings')); ?>">
    <?php wp_nonce_field('woocommerce-settings'); ?>

    <?php $shipping_method->admin_options(); ?>

    <p class="submit">
      <button type="submit" name="save" class="button-primary woocommerce-save-button" value="<?php esc_attr_e('Save changes', 'omnivalt'); ?>">
        <?php esc_html_e('Save changes', 'omnivalt'); ?>
      </button>
    </p>
  </form>
</div>
