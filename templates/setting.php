<?php
defined( 'ABSPATH' ) or die();

/** @var \Gianism\UI\SettingScreen $this */
/** @var GianismMastodon\Mastodon $instance */

?>
	<h3><i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> Mastodon</h3>
	<table class="form-table">
		<tbody>
		<tr>
			<th><label><?php printf( $this->_( 'Connect with %s' ), 'Mastodon' ); ?></label></th>
			<td>
				<?php $this->switch_button( 'mastodon_enabled', $this->option->is_enabled( 'mastodon' ), 1 ); ?>
				<p class="description">
					<?php printf( $this->_( 'See detail at <a href="%1$s">%2$s</a>.' ), $this->setting_url( 'setup' ), $this->_( 'How to set up' ) ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><label for="mastodon_app_name"><?php $this->e( 'App Name' ); ?></label></th>
			<td><input class="regular-text" type="text" name="mastodon_app_name" id="mastodon_app_name"
					value="<?php echo esc_attr( $instance->mastodon_app_name ); ?>"/></td>
		</tr>
		<tr>
			<th><label for="mastodon_login_button_list"><?php $this->e( 'Login Button List' ); ?></label></th>
			<td>
				<textarea id="mastodon_login_button_list" name="mastodon_login_button_list" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->mastodon_login_button_list ) . "\n";
				?></textarea>
				<p class="description">
				<?php printf( $this->_( 'Please describe the domain of instance you want to display as login button, one per line. Please specify the login button to any instance as <code>*</code> (asterisk only).' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="mastodon_deny_instance_list"><?php $this->e( 'Deny Instance List' ); ?></label></th>
			<td>
				<textarea id="mastodon_deny_instance_list" name="mastodon_deny_instance_list" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->mastodon_deny_instance_list ) . "\n";
				?></textarea>
				<p class="description">
				<?php printf( $this->_( 'Please specify the domain of instances that deny login using regular expressions.' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php $this->e( 'Comments linked post' ); ?></th>
			<td>
				<label for="comment_link_enabled"><input type="checkbox" id="comment_link_enabled" name="comment_link_enabled"<?php
					echo ($instance->comment_link_enabled) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'To enable commnets linked post.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="comment_link_acct_consent_explanation"><?php $this->e( 'Consent explanation' ); ?></label></th>
			<td>
				<textarea id="comment_link_acct_consent_explanation" name="comment_link_acct_consent_explanation" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->comment_link_acct_consent_explanation ) . "\n";
				?></textarea>
				<p class="description">
				<?php printf( $this->_( 'Please describe the notes on allowing comments linked posting.' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="comment_link_template"><?php $this->e( 'Comment link template' ); ?></label></th>
			<td>
				<textarea id="comment_link_template" name="comment_link_template" class="large-text code" rows="6"><?php
					echo esc_textarea( $instance->comment_link_template ) . "\n";
				?></textarea>
				<div class="tag_insert_buttons" data-for="comment_link_template">
					<?php $this->e('Available Tags') ?>
					<button type="button">%comment%</button>
					<button type="button">%title%</button>
					<button type="button">%url%</button>
				</div>
				<p class="description">
				<?php printf( $this->_( 'Please describe the template of the comments linked post.' ) ); ?>
				</p>
			</td>
		</tr>
		</tbody>
	</table>
<?php submit_button(); ?>

