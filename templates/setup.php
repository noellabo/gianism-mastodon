<?php

/** @var \Gianism\UI\SettingScreen $this */
?>
<h3><i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> Mastodon</h3>

<p class="description">
	<?php $this->e( 'Mastodon login is very easy. Just enable it.' ); ?>
</p>

<h4>Option. <?php $this->e( 'Enter the application name and the instance to handle specially' ); ?></h4>

<p><?php $this->e( 'Enter the application name to be registered to the mastodon instance. It is recommended to use the name of the site.' ); ?></p>

<table class="gianism-example-table">
	<tbody>
	<tr>
		<th>App Name</th>
		<td><?php printf( $this->_( '<code>%s</code> is recommended.' ), get_bloginfo( 'name' ) ); ?></td>
	</tr>
	<tr>
		<th>Login Button List</th>
		<td>
		<?php $this->e( 'Please describe the domain of instance you want to display as login button, one per line. Please specify the login button to any instance as <code>*</code> (asterisk only).' ); ?>
		</td>
	</tr>
	<tr>
		<th>Deny Instance List</th>
		<td><?php $this->e( 'Please specify the domain of instances that deny login using regular expressions.' ); ?></td>
	</tr>
	</tbody>
</table>
