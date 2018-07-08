<?php

/** @var \Gianism\UI\SettingScreen $this */
?>
<h3><i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> Mastodon</h3>

<p class="description">
	<?php $this->e( 'Mastodon login is very easy. Just enable it.' ); ?>
</p>

<h4><?php $this->e( 'Option.' ); ?> <?php $this->e( 'Enter the application name and the instance to be processed specially' ); ?></h4>

<p><?php $this->e( 'Enter the name of the application to be registered in the mastone instance. We recommend using the name of the site (default value).' ); ?></p>

<table class="gianism-example-table">
	<tbody>
	<tr>
		<th><?php $this->e( 'App Name' ); ?></th>
		<td><?php printf( $this->_( '<code>%s</code> is recommended.' ), get_bloginfo( 'name' ) ); ?></td>
	</tr>
	<tr>
		<th><?php $this->e( 'Login Button List' ); ?></th>
		<td>
		<?php $this->e( 'Please describe the domain of instance you want to display as login button, one per line. Please specify the login button to any instance as <code>*</code> (asterisk only).' ); ?>
		</td>
	</tr>
	<tr>
		<th><?php $this->e( 'Deny Instance List' ); ?></th>
		<td><?php $this->e( 'Please specify the domain of instances that deny login using regular expressions.' ); ?></td>
	</tr>
	</tbody>
</table>
