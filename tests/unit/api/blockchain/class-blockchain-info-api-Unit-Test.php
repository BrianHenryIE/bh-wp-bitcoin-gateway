<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\RawAddress;
use Codeception\Stub\Expected;
use JsonMapper\JsonMapperFactory;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Blockchain_Info_API
 */
class Blockchain_Info_API_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * @covers ::get_received_by_address
	 * @covers ::__construct
	 */
	public function test_get_received_by_address(): void {

		$this->markTestSkipped( 'old test – new we only fetch transactions and calculate the amount received based on the desired number of confirmations' );

		$logger = new ColorLogger();

		$mock_api = $this->make(
			BlockchainInfoApi::class,
			array(
				'getReceivedByAddress' => Expected::once( '10432394445' ),
			)
		);

		$sut = new Blockchain_Info_Api( $logger, $mock_api );

		// Satoshi Nakamoto's address.
		$address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';

		$result = $sut->get_received_by_address( $address, false );

		$this->assertEquals( '104.32394445', (string) $result->getAmount() );
	}

	/**
	 * @covers ::get_address_balance
	 */
	public function test_get_address_balance(): void {

		$this->markTestSkipped( 'old test – new we only fetch transactions and calculate the amount received based on the desired number of confirmations' );

		$logger = new ColorLogger();

		$mock_api = $this->make(
			BlockchainInfoApi::class,
			array(
				'getAddressBalance' => 18142,
			)
		);

		$sut = new Blockchain_Info_Api( $logger, $mock_api );

		// The pizza address.
		$address = '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4';

		$result = $sut->get_address_balance( $address, 1 );

		$this->assertEquals( '0.00018142', (string) $result->get_confirmed_balance()->getAmount() );
	}

	/**
	 * @covers ::get_transactions_received
	 */
	public function test_get_transactions(): void {

		if ( \WP_Mock::usingPatchwork() ) {
			$this->markTestSkipped( 'This test fails when Patchwork is enabled – jsonmapper fails.' );
		}

		$logger = new ColorLogger();

		$mapper = ( new JsonMapperFactory() )->bestFit();
		// $mapper->push( new CaseConversion( TextNotation::UNDERSCORE(), TextNotation::CAMEL_CASE() ) );
		$body        = json_encode(
			(object) array(
				'hash160'        => '05bf3a3aea6335a3949c0a351ff3afcba884e125',
				'address'        => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
				'n_tx'           => 3340,
				'n_unredeemed'   => 5,
				'total_received' => 8143209124126,
				'total_sent'     => 8143209105984,
				'final_balance'  => 18142,
				'txs'            =>
					array(
						0 =>
							(object) array(
								'hash'         => 'ede12da283fd44b71dce0044121d8733908dbe8ab58060f16374076c9cc0700a',
								'ver'          => 2,
								'vin_sz'       => 1,
								'vout_sz'      => 2,
								'size'         => 226,
								'weight'       => 574,
								'fee'          => 1601,
								'relayed_by'   => '0.0.0.0',
								'lock_time'    => 0,
								'tx_index'     => 367340290932974,
								'double_spend' => false,
								'time'         => 1644585199,
								'block_index'  => 722775,
								'block_height' => 722775,
								'inputs'       =>
									array(
										0 =>
											(object) array(
												'sequence' => 0,
												'witness'  => '02483045022100e97f027009b8fedee9a5bd2955b9b07c5514d062ee612ecd1999ee30caedf55502207d591757bc0ad7b2c77d4029708456a1737f2aef88e1546208f4e09c4912b7100121032d5df619b4fdf82000788fff925cb8e19581376f7d58c274257e52ea0a9984e3',
												'script'   => '',
												'index'    => 0,
												'prev_out' =>
													(object) array(
														'spent' => true,
														'script' => '0014e3f32c7a1b8332150080a4fa5cf7df0a63ab5443',
														'spending_outpoints' =>
															array(
																0 =>
																	(object) array(
																		'tx_index' => 367340290932974,
																		'n' => 0,
																	),
															),
														'tx_index' => 5027602054646439,
														'value' => 49572,
														'addr' => 'bc1qu0ejc7smsvep2qyq5na9ea7lpf36k4zrly9ugj',
														'n' => 1,
														'type' => 0,
													),
											),
									),
								'out'          =>
									array(
										0 =>
											(object) array(
												'type'     => 0,
												'spent'    => false,
												'value'    => 2613,
												'spending_outpoints' =>
													array(),
												'n'        => 0,
												'tx_index' => 367340290932974,
												'script'   => '76a91405bf3a3aea6335a3949c0a351ff3afcba884e12588ac',
												'addr'     => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
											),
										1 =>
											(object) array(
												'type'     => 0,
												'spent'    => true,
												'value'    => 45358,
												'spending_outpoints' =>
													array(
														0 =>
															(object) array(
																'tx_index' => 2683426063020175,
																'n' => 0,
															),
													),
												'n'        => 1,
												'tx_index' => 367340290932974,
												'script'   => '001442df8508a66c831f6f5c13fafc62c685c66d6de3',
												'addr'     => 'bc1qgt0c2z9xdjp37m6uz0a0cckxshrx6m0r9jy439',
											),
									),
								'result'       => 2613,
								'balance'      => 18142,
							),
						1 =>
							(object) array(
								'hash'         => 'c7500f09edffdc9b94e0b781d424acbe7110fa07faedd022367561045feedd96',
								'ver'          => 2,
								'vin_sz'       => 1,
								'vout_sz'      => 2,
								'size'         => 225,
								'weight'       => 573,
								'fee'          => 331,
								'relayed_by'   => '0.0.0.0',
								'lock_time'    => 700765,
								'tx_index'     => 5308157796584494,
								'double_spend' => false,
								'time'         => 1631772756,
								'block_index'  => 700790,
								'block_height' => 700790,
								'inputs'       =>
									array(
										0 =>
											(object) array(
												'sequence' => 4294967293,
												'witness'  => '02473044022050fad31933fe4e4e5d54a2eec44c67e8bbb6fe9a7714297cfee5a7f76d6449a4022079feddfaabdf413d917b2c6ce6bba892a68a6ade762f747381c0720be8f29546012102dd63872c3945b3eb161fc27d129559b35b755498b2fbf995ebe0d2e8d88be5ed',
												'script'   => '',
												'index'    => 0,
												'prev_out' =>
													(object) array(
														'spent' => true,
														'script' => '00140051287313dbcd959e6bf463b0b045938a51ffd6',
														'spending_outpoints' =>
															array(
																0 =>
																	(object) array(
																		'tx_index' => 5308157796584494,
																		'n' => 0,
																	),
															),
														'tx_index' => 5078139211114845,
														'value' => 30497,
														'addr' => 'bc1qqpgjsucnm0xet8nt733mpvz9jw99rl7kpx9p7k',
														'n' => 0,
														'type' => 0,
													),
											),
									),
								'out'          =>
									array(
										0 =>
											(object) array(
												'type'     => 0,
												'spent'    => false,
												'value'    => 12217,
												'spending_outpoints' =>
													array(),
												'n'        => 0,
												'tx_index' => 5308157796584494,
												'script'   => '76a91405bf3a3aea6335a3949c0a351ff3afcba884e12588ac',
												'addr'     => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
											),
										1 =>
											(object) array(
												'type'     => 0,
												'spent'    => false,
												'value'    => 17949,
												'spending_outpoints' =>
													array(),
												'n'        => 1,
												'tx_index' => 5308157796584494,
												'script'   => '0014e0cdf6bdac2e1ca21cc88cb44525a509ea8bb093',
												'addr'     => 'bc1qurxld0dv9cw2y8xg3j6y2fd9p84ghvynu9cms8',
											),
									),
								'result'       => 12217,
								'balance'      => 15529,
							),
						2 =>
							(object) array(
								'hash'         => '0c2320a78446e3b51bd01e68e49da0fd5ac48ff005265a184428f6737a7324fe',
								'ver'          => 1,
								'vin_sz'       => 1,
								'vout_sz'      => 2,
								'size'         => 225,
								'weight'       => 900,
								'fee'          => 1000,
								'relayed_by'   => '0.0.0.0',
								'lock_time'    => 0,
								'tx_index'     => 8941840309845701,
								'double_spend' => false,
								'time'         => 1568245468,
								'block_index'  => 594433,
								'block_height' => 594433,
								'inputs'       =>
									array(
										0 =>
											(object) array(
												'sequence' => 4027431614,
												'witness'  => '',
												'script'   => '4730440220359696b5ad6c4fe988d36e006b8266c442db29a0e4cf344d14bfd5b65cb4be210220661d6eb51356ede37a0f24e10a87c12e5046968fb726a73cea25d89d9fd4db410121037a5d0d2f018c4a74d2b8dd2001cc3bf2d7a5a795345c8d09f6953b4c632dae45',
												'index'    => 0,
												'prev_out' =>
													(object) array(
														'spent' => true,
														'script' => '76a9142ccebd59f571d52d7bb345250a705138a878dd0488ac',
														'spending_outpoints' =>
															array(
																0 =>
																	(object) array(
																		'tx_index' => 8941840309845701,
																		'n' => 0,
																	),
															),
														'tx_index' => 6749672941733094,
														'value' => 173960,
														'addr' => '155vPxtHAuS6WiM4613eniLRS9wPiAEN6c',
														'n' => 1,
														'type' => 0,
													),
											),
									),
								'out'          =>
									array(
										0 =>
											(object) array(
												'type'     => 0,
												'spent'    => false,
												'value'    => 1000,
												'spending_outpoints' =>
													array(),
												'n'        => 0,
												'tx_index' => 8941840309845701,
												'script'   => '76a91405bf3a3aea6335a3949c0a351ff3afcba884e12588ac',
												'addr'     => '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4',
											),
										1 =>
											(object) array(
												'type'     => 0,
												'spent'    => true,
												'value'    => 171960,
												'spending_outpoints' =>
													array(
														0 =>
															(object) array(
																'tx_index' => 1733666378454630,
																'n' => 0,
															),
													),
												'n'        => 1,
												'tx_index' => 8941840309845701,
												'script'   => '76a9142ccebd59f571d52d7bb345250a705138a878dd0488ac',
												'addr'     => '155vPxtHAuS6WiM4613eniLRS9wPiAEN6c',
											),
									),
								'result'       => 1000,
								'balance'      => 3312,
							),
					),
			)
		);
		$raw_address = $mapper->mapToClassFromString( $body, RawAddress::class );

		$mock_api = $this->make(
			BlockchainInfoApi::class,
			array(
				'getRawAddr' => $raw_address,
			)
		);

		$sut = new Blockchain_Info_Api( $logger, $mock_api );

		// The pizza address.
		$address = '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4';

		$result = $sut->get_transactions_received( $address );

		$first = array_shift( $result );

		$this->assertEquals( '0.00002613', (string) $first?->get_value( $address )->getAmount() );
		// $this->assertEquals( '0.00047971', (string) $first->get_value( $address )->getAmount() );
	}
}
