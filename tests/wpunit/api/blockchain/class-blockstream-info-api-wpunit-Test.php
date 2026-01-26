<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\ColorLogger\ColorLogger;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Blockstream_Info_API
 */
class Blockstream_Info_API_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::__construct
	 */
	public function test_get_balance(): void {
		$this->markTestSkipped( 'old test – preserving this test so it can be used when the API is extracted to its own package.' );

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode(
				array(
					'address'       => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
					'chain_stats'   =>
						array(
							'funded_txo_count' => 3304,
							'funded_txo_sum'   => 8143209124126,
							'spent_txo_count'  => 3299,
							'spent_txo_sum'    => 8143209105984,
							'tx_count'         => 3340,
						),
					'mempool_stats' =>
						array(
							'funded_txo_count' => 0,
							'funded_txo_sum'   => 0,
							'spent_txo_count'  => 0,
							'spent_txo_sum'    => 0,
							'tx_count'         => 0,
						),
				)
			),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		// The pizza address.
		$address = '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4';

		$result = $sut->get_address_balance( $address, 1 );

		$this->assertEquals( '0.00018142', (string) $result->get_confirmed_balance()->getAmount() );
	}

	/**
	 * TODO: Run a contract test to check our transaction calculations match direct API calls.
	 */
	public function test_get_received_by_address(): void {
		$this->markTestSkipped( 'old test – preserving this test so it can be used when the API is extracted to its own package.' );

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode(
				array(
					'address'       => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
					'chain_stats'   =>
						array(
							'funded_txo_count' => 3304,
							'funded_txo_sum'   => 8143209124126,
							'spent_txo_count'  => 3299,
							'spent_txo_sum'    => 8143209105984,
							'tx_count'         => 3340,
						),
					'mempool_stats' =>
						array(
							'funded_txo_count' => 0,
							'funded_txo_sum'   => 0,
							'spent_txo_count'  => 0,
							'spent_txo_sum'    => 0,
							'tx_count'         => 0,
						),
				)
			),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		// The pizza address.
		$address = '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4';

		$result = $sut->get_received_by_address( $address, true );

		$this->assertEquals( '81432.09124126', $result->getAmount() );
	}

	/**
	 * @covers ::get_transactions_received
	 *
	 * @see https://esplora.blockstream.com/tx/8e5e6b898750a7afbe683a953fbf30bd990bb57ccd2d904c76df29f61054e743
	 */
	public function test_get_transactions(): void {

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode(
				array(
					0 =>
						array(
							'txid'     => '8e5e6b898750a7afbe683a953fbf30bd990bb57ccd2d904c76df29f61054e743',
							'version'  => 1,
							'locktime' => 0,
							'vin'      =>
								array(
									0 =>
										array(
											'txid'        => 'd482ceff793a908c3bd574a4f41f392c80bccc127530209f09c12b97f226bf2b',
											'vout'        => 0,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a914f329377f8f4c6f9e4a532bfb99b06d110d5277ab88ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 f329377f8f4c6f9e4a532bfb99b06d110d5277ab OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '1PAiaQe6KEaEtCQEpLLnLF57QyiuCp42H8',
													'value' => 752664,
												),
											'scriptsig'   => '483045022100d990e9b0d35a76da1c5e2d719d20fa2504897f7eec12b630cc08751bb220560b022070d36f144d17ef8cc3ff3262a419122fc6964725df6095826cdd77a93a0334a30121035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'scriptsig_asm' => 'OP_PUSHBYTES_72 3045022100d990e9b0d35a76da1c5e2d719d20fa2504897f7eec12b630cc08751bb220560b022070d36f144d17ef8cc3ff3262a419122fc6964725df6095826cdd77a93a0334a301 OP_PUSHBYTES_33 035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
									1 =>
										array(
											'txid'        => '3c4967627b014de6130a106c4754c567f1f62619690a9910ee479e3f7ee125da',
											'vout'        => 0,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a914f329377f8f4c6f9e4a532bfb99b06d110d5277ab88ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 f329377f8f4c6f9e4a532bfb99b06d110d5277ab OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '1PAiaQe6KEaEtCQEpLLnLF57QyiuCp42H8',
													'value' => 832293,
												),
											'scriptsig'   => '483045022100b9b72e676237bc72c2a66b2739653b1b1397689180432912ed715e1d60d2ab8a022040bee911cab89b51de76bffe99b5051fa46eae5a38fc7099303576793f98293e0121035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'scriptsig_asm' => 'OP_PUSHBYTES_72 3045022100b9b72e676237bc72c2a66b2739653b1b1397689180432912ed715e1d60d2ab8a022040bee911cab89b51de76bffe99b5051fa46eae5a38fc7099303576793f98293e01 OP_PUSHBYTES_33 035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
									2 =>
										array(
											'txid'        => '625e98915b0524cede24194cc2c89e3a18e923be2798206d81fe34471a2be938',
											'vout'        => 59,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a914f329377f8f4c6f9e4a532bfb99b06d110d5277ab88ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 f329377f8f4c6f9e4a532bfb99b06d110d5277ab OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '1PAiaQe6KEaEtCQEpLLnLF57QyiuCp42H8',
													'value' => 879576,
												),
											'scriptsig'   => '473044022006134a29f0eb64a0e5476778b7862143abcb814aa62224a53a969594ec99d98402206db0126fcb3e13eb1d65126eff5f4ad162499d62b28e5134d8165f2bbdb3316a0121035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'scriptsig_asm' => 'OP_PUSHBYTES_71 3044022006134a29f0eb64a0e5476778b7862143abcb814aa62224a53a969594ec99d98402206db0126fcb3e13eb1d65126eff5f4ad162499d62b28e5134d8165f2bbdb3316a01 OP_PUSHBYTES_33 035ed20ff573ca7c0a406307de0fb47dfc62361a873162cbe71fe6712b101942d2',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
								),
							'vout'     =>
								array(
									0 =>
										array(
											'scriptpubkey' => '76a914660d4ef3a743e3e696ad990364e555c271ad504b88ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 660d4ef3a743e3e696ad990364e555c271ad504b OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F',
											'value'        => 2413000,
										),
									1 =>
										array(
											'scriptpubkey' => '76a914f329377f8f4c6f9e4a532bfb99b06d110d5277ab88ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 f329377f8f4c6f9e4a532bfb99b06d110d5277ab OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '1PAiaQe6KEaEtCQEpLLnLF57QyiuCp42H8',
											'value'        => 2465,
										),
								),
							'size'     => 521,
							'weight'   => 2084,
							'fee'      => 49068,
							'status'   =>
								array(
									'confirmed'    => true,
									'block_height' => 643714,
									'block_hash'   => '0000000000000000000c6339021601f591f80e35b649e0b8990cdd929f99203b',
									'block_time'   => 1597425899,
								),
						),
					1 =>
						array(
							'txid'     => 'b357ef869a27affd4442e57367396dc404b5757da117d8903ef196fd021b57bc',
							'version'  => 1,
							'locktime' => 0,
							'vin'      =>
								array(
									0 =>
										array(
											'txid'        => '7860616d98eeccf1743a4050119e667cff543bd086c15367fe3c3680dc7cca28',
											'vout'        => 0,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a914938d4cb228b4b3fb294760a7f2bcdfee8bda7b2a88ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 938d4cb228b4b3fb294760a7f2bcdfee8bda7b2a OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '1ETBbsHPvbydW7hGWXXKXZ3pxVh3VFoMaX',
													'value' => 201153999,
												),
											'scriptsig'   => '4730440220d32749cd81f6a0d15bb83b6882045c8020459d04bae38d7603117d26220f155e0220eea4dbf89e822324c58307fba71a70d5be82746f5b952b9e47be17f1cb3cd53b014104c46d6462f67f990211d3a7077f005e67154f5f785b3edc06af3de62649a15bad35905fa7af9f272f80379a41525ad57a2245c2edc4807e3e49f43eb1c1b11979',
											'scriptsig_asm' => 'OP_PUSHBYTES_71 30440220d32749cd81f6a0d15bb83b6882045c8020459d04bae38d7603117d26220f155e0220eea4dbf89e822324c58307fba71a70d5be82746f5b952b9e47be17f1cb3cd53b01 OP_PUSHBYTES_65 04c46d6462f67f990211d3a7077f005e67154f5f785b3edc06af3de62649a15bad35905fa7af9f272f80379a41525ad57a2245c2edc4807e3e49f43eb1c1b11979',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
									1 =>
										array(
											'txid'        => '95872d224d3f8e9da7a6d62f8c478897c6f0da82178c6e88e9cedf6c1d2a0ce1',
											'vout'        => 1,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a914660d4ef3a743e3e696ad990364e555c271ad504b88ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 660d4ef3a743e3e696ad990364e555c271ad504b OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F',
													'value' => 4678300000,
												),
											'scriptsig'   => '4730440220be57a3a79c5d38766059998e8eb18f32ec25676bd0f573f5fafb0d497db517a10220af7d8cb0e78331d08f8d443129f8e28e0632fa0a87bd8ce8f8119346c629d1e4014104cc71eb30d653c0c3163990c47b976f3fb3f37cccdcbedb169a1dfef58bbfbfaff7d8a473e7e2e6d317b87bafe8bde97e3cf8f065dec022b51d11fcdd0d348ac4',
											'scriptsig_asm' => 'OP_PUSHBYTES_71 30440220be57a3a79c5d38766059998e8eb18f32ec25676bd0f573f5fafb0d497db517a10220af7d8cb0e78331d08f8d443129f8e28e0632fa0a87bd8ce8f8119346c629d1e401 OP_PUSHBYTES_65 04cc71eb30d653c0c3163990c47b976f3fb3f37cccdcbedb169a1dfef58bbfbfaff7d8a473e7e2e6d317b87bafe8bde97e3cf8f065dec022b51d11fcdd0d348ac4',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
								),
							'vout'     =>
								array(
									0 =>
										array(
											'scriptpubkey' => '76a91406f1b66ffe49df7fce684df16c62f59dc9adbd3f88ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 06f1b66ffe49df7fce684df16c62f59dc9adbd3f OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp',
											'value'        => 800000000,
										),
									1 =>
										array(
											'scriptpubkey' => '76a914938d4cb228b4b3fb294760a7f2bcdfee8bda7b2a88ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 938d4cb228b4b3fb294760a7f2bcdfee8bda7b2a OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '1ETBbsHPvbydW7hGWXXKXZ3pxVh3VFoMaX',
											'value'        => 4079453999,
										),
								),
							'size'     => 436,
							'weight'   => 1744,
							'fee'      => 0,
							'status'   =>
								array(
									'confirmed'    => true,
									'block_height' => 183579,
									'block_hash'   => '00000000000004ab94421aa2362535a111cb2039742c3fb44437a933517f94fa',
									'block_time'   => 1339172906,
								),
						),
					2 =>
						array(
							'txid'     => '95872d224d3f8e9da7a6d62f8c478897c6f0da82178c6e88e9cedf6c1d2a0ce1',
							'version'  => 1,
							'locktime' => 0,
							'vin'      =>
								array(
									0 =>
										array(
											'txid'        => '2dc7e6827a9a6a86a1f22022bcf6dca1f92a9e4f64cc224db2a6e5c2eaaa3499',
											'vout'        => 0,
											'prevout'     =>
												array(
													'scriptpubkey' => '76a9142d441ae7aa4c8c08f042b37740ce5b96e9f9f08288ac',
													'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 2d441ae7aa4c8c08f042b37740ce5b96e9f9f082 OP_EQUALVERIFY OP_CHECKSIG',
													'scriptpubkey_type' => 'p2pkh',
													'scriptpubkey_address' => '158Lzf8pRsJ2sDVKCw3n7swXVMmBu71pX7',
													'value' => 5915000000,
												),
											'scriptsig'   => '493046022100dde634c615a06d884bfc2a8fc5791e4c98e6a9948b4fb0c9ccd91c514360661a0221008274da0c7bd177054e75d4e1afcd55fd2d84096896a197bef7a9fc93b048bd23014104524d1560bed543801277984a8dcc384524ab91fb744c1296d5cf89ffc8d287b70a573b03cb7adaadff53863c5b9c52a0704eba97228d9100c0cf6f75a267826e',
											'scriptsig_asm' => 'OP_PUSHBYTES_73 3046022100dde634c615a06d884bfc2a8fc5791e4c98e6a9948b4fb0c9ccd91c514360661a0221008274da0c7bd177054e75d4e1afcd55fd2d84096896a197bef7a9fc93b048bd2301 OP_PUSHBYTES_65 04524d1560bed543801277984a8dcc384524ab91fb744c1296d5cf89ffc8d287b70a573b03cb7adaadff53863c5b9c52a0704eba97228d9100c0cf6f75a267826e',
											'is_coinbase' => false,
											'sequence'    => 4294967295,
										),
								),
							'vout'     =>
								array(
									0 =>
										array(
											'scriptpubkey' => '76a91450bb08c90f7fb7321069e3d6676c5b08242d5dc688ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 50bb08c90f7fb7321069e3d6676c5b08242d5dc6 OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '18Ms7igboNUpe3JrHUPzqNvU8qSvwScDVQ',
											'value'        => 1236700000,
										),
									1 =>
										array(
											'scriptpubkey' => '76a914660d4ef3a743e3e696ad990364e555c271ad504b88ac',
											'scriptpubkey_asm' => 'OP_DUP OP_HASH160 OP_PUSHBYTES_20 660d4ef3a743e3e696ad990364e555c271ad504b OP_EQUALVERIFY OP_CHECKSIG',
											'scriptpubkey_type' => 'p2pkh',
											'scriptpubkey_address' => '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F',
											'value'        => 4678300000,
										),
								),
							'size'     => 259,
							'weight'   => 1036,
							'fee'      => 0,
							'status'   =>
								array(
									'confirmed'    => true,
									'block_height' => 183546,
									'block_hash'   => '0000000000000245124a1b0f6f3e41d97ef5a34b4099e75d4babb1e04ddd8850',
									'block_time'   => 1339152347,
								),
						),
				)
			),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		$address = '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F';

		$result = $sut->get_transactions_received( $address );
		assert( 0 < count( $result ) );
		$first = array_shift( $result );

		// $this->assertEquals( '0.02413', $first->get_value( $address )->getAmount() );
		// TODO: Move the above JSON to a fixture. And write a real test.
		$this->assertEquals( '8e5e6b898750a7afbe683a953fbf30bd990bb57ccd2d904c76df29f61054e743', $first->get_txid() );
	}

	/**
	 * @covers ::get_transactions_received
	 */
	public function test_get_transactions_received_logs_debug_with_url(): void {

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode( array() ),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		$address = '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F';

		$sut->get_transactions_received( $address );

		$this->assertTrue( $logger->hasDebugRecords() );
		$this->assertTrue( $logger->hasDebugThatContains( 'URL:' ) );
	}

	/**
	 * @covers ::get_transactions_received
	 */
	public function test_get_transactions_received_logs_debug_with_url_in_context(): void {

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode( array() ),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		$address = '1AJbsFZ64EpEfS5UAjAfcUG8pH8Jn3rn1F';

		$sut->get_transactions_received( $address );

		// Check that context contains the URL with the address.
		$expected_url         = "https://blockstream.info/api/address/{$address}/txs";
		$has_expected_context = false;
		foreach ( $logger->records as $record ) {
			/** @var array{level:string,context:array{url?:string}} $record */
			if ( 'debug' === $record['level']
				&& isset( $record['context']['url'] )
				&& $expected_url === $record['context']['url'] ) {
				$has_expected_context = true;
				break;
			}
		}

		$this->assertTrue( $has_expected_context, 'Should log URL in debug context with value: ' . $expected_url );
	}

	/**
	 * @covers ::get_transactions_received
	 */
	public function test_get_transactions_received_logs_debug_with_correct_url_for_different_address(): void {

		$logger = new ColorLogger();

		$sut = new Blockstream_Info_API( $logger );

		$request_response = array(
			'body'     => wp_json_encode( array() ),
			'response' => array(
				'code' => 200,
			),
		);

		add_filter(
			'pre_http_request',
			function () use ( $request_response ) {
				return $request_response;
			}
		);

		$address = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

		$sut->get_transactions_received( $address );

		// Check that context contains the URL with the correct address.
		$expected_url         = "https://blockstream.info/api/address/{$address}/txs";
		$has_expected_context = false;
		foreach ( $logger->records as $record ) {
			/** @var array{level:string,context:array{url?:string}} $record */
			if ( 'debug' === $record['level']
				&& isset( $record['context']['url'] )
				&& $expected_url === $record['context']['url'] ) {
				$has_expected_context = true;
				break;
			}
		}

		$this->assertTrue( $has_expected_context, 'Should log URL in debug context with value: ' . $expected_url );
	}
}
