<div class="shazabo-manager-enquiry-form" id="shazabo-manager-enquiry-form">

    <form action="" method="post">

        <div class="form-row">
            <label for="name"><?php _e('Name', 'shazabo-manager'); ?></label>

            <input type="text" id="name" name="name" value="" required>
        </div>

        <div class="form-row">
            <label for="email"><?php _e('E-Mail', 'shazabo-manager'); ?></label>

            <input type="email" id="email" name="email" value="" required>
        </div>

        <div class="form-row">
            <label for="message"><?php _e('Message', 'shazabo-manager'); ?></label>

            <textarea name="message" id="message" required></textarea>
        </div>

        <div class="form-row">

            <?php wp_nonce_field('shazabo-manager-enquiry-form'); ?>

            <input type="hidden" class="hidden" name="action" value="shazabo_manager_enquiry" />
            <input type="submit" class="submit-enquiry" name="send_enquiry" value="<?php esc_attr_e('Send Enquiry', 'shazabo-manager'); ?>" />
        </div>

    </form>
</div>