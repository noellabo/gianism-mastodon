<?php
defined( 'ABSPATH' ) or die();

/** @var \Gianism\UI\SettingScreen $this */
/** @var GianismMastodon\Mastodon $instance */

$visibilities = [
	'public' => $this->_( 'Public: Post to public timelines' ),
	'unlisted' => $this->_( 'Unlisted: Do not post to public timelines' ),
	'private' => $this->_( 'Private: Post to followers only' ),
	'direct' => $this->_( 'Direct: Post to mentioned users only' ),
];

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
		</tbody>
	</table>

	<h4><?php $this->e( 'Linked Post settings' ); ?></h4>
	<table class="form-table">
		<tbody>
		<tr>
			<th><?php $this->e( 'Linked post' ); ?></th>
			<td>
				<label for="post_link_enabled"><input type="checkbox" id="post_link_enabled" name="post_link_enabled"<?php
					echo ($instance->post_link_enabled) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'To enable linked post.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><?php $this->e( 'Manual link posting' ); ?></th>
			<td>
				<label for="post_link_manual"><input type="checkbox" id="post_link_manual" name="post_link_manual"<?php
					echo ($instance->post_link_manual) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'Linked Post manually.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="post_link_visibility"><?php $this->e( 'Linked post visibility' ); ?></label></th>
			<td>
				<select name="post_link_visibility" id="post_link_visibility">
				<?php
					foreach( $visibilities as $key => $value ) {
						printf('<option value="%s"%s>%s</option>',
							$key,
							($instance->post_link_visibility == $key) ? ' selected="selected"' : '',
							$value
						);
					}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php $this->e( 'Linked post sensitive' ); ?></th>
			<td>
				<label for="post_link_sensitive"><input type="checkbox" id="post_link_sensitive" name="post_link_sensitive"<?php
					echo ($instance->post_link_sensitive) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'To enable CW of linked post.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="post_link_title_template"><?php $this->e( 'Post link title template' ); ?></label></th>
			<td>
				<textarea id="post_link_title_template" name="post_link_title_template" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->post_link_title_template ) . "\n";
				?></textarea>
				<div class="tag_insert_buttons" data-for="post_link_title_template">
					<div>
						<i class="far fa-plus-square"></i>
						<span class="detail-switch"><?php $this->e('Available Tags') ?></span>
					</div>
					<button type="button" title="<?php $this->e('Title of the article') ?>">%title%</button>
					<button type="button" title="<?php $this->e('Slug of the article') ?>">%slug%</button>
					<button type="button" title="<?php $this->e('Category name of the article (Only the first one)') ?>">%category_name%</button>
					<button type="button" title="<?php $this->e('Category slug of the article (Only the first one)') ?>">%category_slug%</button>
					<button type="button" title="<?php $this->e('Category names of the article') ?>">%category_names%</button>
					<button type="button" title="<?php $this->e('Category slugs of the article') ?>">%category_slugs%</button>
					<button type="button" title="<?php $this->e('Tag names of the article') ?>">%tag_names%</button>
					<button type="button" title="<?php $this->e('Tag slugs of the article') ?>">%tag_slugs%</button>
					<button type="button" title="<?php $this->e('URL of article') ?>">%post_url%</button>
					<button type="button" title="<?php $this->e('URL of this site home') ?>">%home_url%</button>
					<button type="button" title="<?php printf($this->_('Name of this site - &quot;%s&quot;'), get_bloginfo( 'name' )) ?>">%site_name%</button>
				</div>
				<p class="description">
				<?php printf( $this->_( 'Please describe the Contents Warning (CW) template of the linked post.' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="post_link_content_template"><?php $this->e( 'Post link content template' ); ?></label></th>
			<td>
				<textarea id="post_link_content_template" name="post_link_content_template" class="large-text code" rows="6"><?php
					echo esc_textarea( $instance->post_link_content_template ) . "\n";
				?></textarea>
				<div class="tag_insert_buttons" data-for="post_link_content_template">
					<div>
						<i class="far fa-plus-square"></i>
						<span class="detail-switch"><?php $this->e('Available Tags') ?></span>
					</div>
					<button type="button" title="<?php $this->e('Post contents') ?>">%content%</button>
					<button type="button" title="<?php $this->e('Post excerpt') ?>">%post_excerpt%</button>
					<button type="button" title="<?php $this->e('Title of the article') ?>">%title%</button>
					<button type="button" title="<?php $this->e('Slug of the article') ?>">%slug%</button>
					<button type="button" title="<?php $this->e('Category name of the article (Only the first one)') ?>">%category_name%</button>
					<button type="button" title="<?php $this->e('Category slug of the article (Only the first one)') ?>">%category_slug%</button>
					<button type="button" title="<?php $this->e('Category names of the article') ?>">%category_names%</button>
					<button type="button" title="<?php $this->e('Category slugs of the article') ?>">%category_slugs%</button>
					<button type="button" title="<?php $this->e('Tag names of the article') ?>">%tag_names%</button>
					<button type="button" title="<?php $this->e('Tag slugs of the article') ?>">%tag_slugs%</button>
					<button type="button" title="<?php $this->e('URL of article') ?>">%post_url%</button>
					<button type="button" title="<?php $this->e('URL of this site home') ?>">%home_url%</button>
					<button type="button" title="<?php printf($this->_('Name of this site - &quot;%s&quot;'), get_bloginfo( 'name' )) ?>">%site_name%</button>
				</div>
				<p class="description">
				<?php printf( $this->_( 'Please describe the content template of the comments linked post.' ) ); ?>
				</p>
			</td>
		</tr>
		</tbody>
	</table>

	<h4><?php $this->e( 'Linked Comments settings' ); ?></h4>
	<table class="form-table">
		<tbody>
		<tr>
			<th><?php $this->e( 'Comments linked post' ); ?></th>
			<td>
				<label for="comment_link_enabled"><input type="checkbox" id="comment_link_enabled" name="comment_link_enabled"<?php
					echo ($instance->comment_link_enabled) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'To enable commnets linked post.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="comment_link_visibility"><?php $this->e( 'Comments linked post visibility' ); ?></label></th>
			<td>
				<select name="comment_link_visibility" id="comment_link_visibility">
				<?php
					foreach( $visibilities as $key => $value ) {
						printf('<option value="%s"%s>%s</option>',
							$key,
							($instance->comment_link_visibility == $key) ? ' selected="selected"' : '',
							$value
						);
					}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php $this->e( 'Comments linked post sensitive' ); ?></th>
			<td>
				<label for="comment_link_sensitive"><input type="checkbox" id="comment_link_sensitive" name="comment_link_sensitive"<?php
					echo ($instance->comment_link_sensitive) ? ' checked="checked"' : '' ?>>
				<?php printf( $this->_( 'To enable CW of commnets linked post.' ) ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="comment_link_title_template"><?php $this->e( 'Comment link title template' ); ?></label></th>
			<td>
				<textarea id="comment_link_title_template" name="comment_link_title_template" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->comment_link_title_template ) . "\n";
				?></textarea>
				<div class="tag_insert_buttons" data-for="comment_link_title_template">
					<div>
						<i class="far fa-plus-square"></i>
						<span class="detail-switch"><?php $this->e('Available Tags') ?></span>
					</div>
					<button type="button" title="<?php $this->e('Title of the commented article') ?>">%title%</button>
					<button type="button" title="<?php $this->e('Slug of the commented article') ?>">%slug%</button>
					<button type="button" title="<?php $this->e('URL of commented article') ?>">%post_url%</button>
					<button type="button" title="<?php $this->e('URL of this site home') ?>">%home_url%</button>
					<button type="button" title="<?php printf($this->_('Name of this site - &quot;%s&quot;'), get_bloginfo( 'name' )) ?>">%site_name%</button>
				</div>
				<p class="description">
				<?php printf( $this->_( 'Please describe the Contents Warning (CW) template of the comments linked post.' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="comment_link_content_template"><?php $this->e( 'Comment link content template' ); ?></label></th>
			<td>
				<textarea id="comment_link_content_template" name="comment_link_content_template" class="large-text code" rows="6"><?php
					echo esc_textarea( $instance->comment_link_content_template ) . "\n";
				?></textarea>
				<div class="tag_insert_buttons" data-for="comment_link_content_template">
					<div>
						<i class="far fa-plus-square"></i>
						<span class="detail-switch"><?php $this->e('Available Tags') ?></span>
					</div>
					<button type="button" title="<?php $this->e('Comment contents') ?>">%content%</button>
					<button type="button" title="<?php $this->e('Title of the commented article') ?>">%title%</button>
					<button type="button" title="<?php $this->e('Slug of the commented article') ?>">%slug%</button>
					<button type="button" title="<?php $this->e('URL of commented article') ?>">%post_url%</button>
					<button type="button" title="<?php $this->e('URL of this site home') ?>">%home_url%</button>
					<button type="button" title="<?php printf($this->_('Name of this site - &quot;%s&quot;'), get_bloginfo( 'name' )) ?>">%site_name%</button>
				</div>
				<p class="description">
				<?php printf( $this->_( 'Please describe the content template of the comments linked post.' ) ); ?>
				</p>
			</td>
		</tr>
		</tbody>
	</table>

	<h4><?php $this->e( 'Other settings' ); ?></h4>
	<table class="form-table">
		<tbody>
		<tr>
			<th><label for="mastodon_content_length_max"><?php $this->e( 'Maximum content length per link post' ); ?></label></th>
			<td><input class="regular-text" type="number" name="mastodon_content_length_max" id="mastodon_content_length_max"
					value="<?php echo esc_attr( $instance->mastodon_content_length_max ); ?>"/></td>
		</tr>
		<tr>
			<th><label for="acct_consent_explanation"><?php $this->e( 'Consent explanation' ); ?></label></th>
			<td>
				<textarea id="acct_consent_explanation" name="acct_consent_explanation" class="large-text code" rows="3"><?php
					echo esc_textarea( $instance->acct_consent_explanation ) . "\n";
				?></textarea>
				<p class="description">
				<?php printf( $this->_( 'Please describe the notes on allowing linked posting.' ) ); ?>
				</p>
			</td>
		</tr>
		</tbody>
	</table>
<?php submit_button(); ?>

