<?php
/**
 * Class SampleTest
 *
 * @package Gianism_Mastodon
 */

use GianismMastodon\InstanceRegistry;

class MastodonTest extends WP_UnitTestCase {
	private $mastodon_client;

	public function __construct() {
		$this->mastodon_client = InstanceRegistry::get_instance();
	}

	public function test_get_provider() {
		// $provider = $this->mastodon_client->get_provider( 'dtp-mstdn.jp', 'localhost:3000' );
		// $this->assertNotEquals( false, $provider );
	}

	public function test_get_valid_domain() {
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( 'https://dtp-mstdn.jp' ) );
		$this->assertEquals( 'http://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( 'http://dtp-mstdn.jp' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( 'ftp://dtp-mstdn.jp' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( 'mailto:noellabo@dtp-mstdn.jp' ) );
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( '//dtp-mstdn.jp' ) );
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( 'dtp-mstdn.jp' ) );
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( '@dtp-mstdn.jp' ) );
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( 'noellabo@dtp-mstdn.jp' ) );
		$this->assertEquals( 'https://dtp-mstdn.jp', $this->mastodon_client->get_valid_domain( '@noellabo@dtp-mstdn.jp' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( '' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( '@noellabo@a@dtp-mstdn.jp' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( 'a@noellabo@dtp-mstdn.jp' ) );
		$this->assertEquals( false, $this->mastodon_client->get_valid_domain( 'mailto:dtp-mstdn.jp' ) );
	}
}


