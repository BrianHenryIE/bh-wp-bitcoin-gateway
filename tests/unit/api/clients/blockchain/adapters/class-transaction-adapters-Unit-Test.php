<?php
/**
 * Checks the API adapters each return the same values for the same transaction.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\JsonMapper\AssociativeArrayMiddleware;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Enums\TextNotation;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\FactoryRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\PropertyMapper;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperBuilder;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Middleware\CaseConversion;
use Codeception\Test\Unit;
use DateTimeImmutable;
use WP_Mock;

class Transaction_Adapters_Unit_Test extends Unit {

	/**
	 * @return array<array{0:class-string, 1:string, 2:string}>
	 */
	public static function all_adapters_match_data_provider(): array {

		return array(
			array(
				Blockchain_Info_Api_Transaction_Adapter::class,
				Transaction::class,
				'{ "hash": "44032b210862cb2bdf549ac95c7d1a40e7fc2f81b42948047f818029a7f95c3b", "ver": 1, "vin_sz": 2, "vout_sz": 2, "size": 736, "weight": 1426, "fee": 25878, "relayed_by": "0.0.0.0", "lock_time": 689297, "tx_index": 2088656368381968, "double_spend": false, "time": 1625135102, "block_index": 689299, "block_height": 689299, "inputs": [ { "sequence": 4294967295, "witness": "040047304402201874a90b1b3bba93065e36fbc0ef0917e54b3c07eff8629b09ef2a05b8fe5108022050cc104ddc0f81b9da79598e555d5d697be5421924cb557c55711713a978889d01473044022074fb0a4fefbac30331fb6e173dbd1d3f08fae124060ff0804f55d7991aa5b5bf022061e02aecf3f91468719ced6e0c126e87edab615ebbda0579eca55f44c62c29050169522103407b5f92f2b01aa32acc7fc73d1274d8e8333f92d2e0748c069223b6f49463db21032910f98af72cc3973d29f7597d566cd28804b16977c89a2cdbd38248f64059e0210235b564577d986dad59ac83db3715bfd8e2d64992ae4da93b0ec9c5a0bb8d821053ae", "script": "2200204db0a225378ec24fde1ebf5f8f423893448aa9d1fae079b5d741f07795c9a798", "index": 0, "prev_out": { "type": 0, "spent": true, "value": 270000, "spending_outpoints": [ { "tx_index": 2088656368381968, "n": 0 } ], "n": 1, "tx_index": 6682118218815207, "script": "a914c15f1ad5162b35d8ddb3cf46009326d36252237187", "addr": "3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum" } }, { "sequence": 4294967295, "witness": "040047304402200f8ea1dfc0dc3a53bda3e38a5327687cfe26f3b24d6e56e7d5e47f2cb4824cd9022002a05317cf5a6444bb901a5376a1684fbacb77fd1c564ffde3880faab4fac9670147304402204c833ee43ea8c44f008687ca64059dc5cd021fbff342954a0bc3c932b19b1a85022018c31d211770c965abb3ab075e6d31017c0dacb5fd2c0fe72c3411216c298d4b0169522103908cfe604e96160c5cd3065edd0bd55f2b274ca487d97efeffffa889141d760621035b327dad888bbac6055c62e6011d5fd928a2878383f49ba594ef980c84c5222f210236f6c921ffdca75285a138e67584b006faa7ef5d117f5113b7796371cbb2a5d153ae", "script": "220020751208a9a3d43dcdcbf3f7f6990609c52af221519cab520d9d5234d1e67ac093", "index": 1, "prev_out": { "type": 0, "spent": true, "value": 1092000, "spending_outpoints": [ { "tx_index": 2088656368381968, "n": 1 } ], "n": 17, "tx_index": 1614840574951204, "script": "a914d68249e1c0a6fe0d2702477f561dc7cbd567e50987", "addr": "3MFEcCunCKtrmZ5qAVQsRo5icgd4iasZ1w" } } ], "out": [ { "type": 0, "spent": true, "value": 396122, "spending_outpoints": [ { "tx_index": 5381006970980833, "n": 5 } ], "n": 0, "tx_index": 2088656368381968, "script": "76a914b128a48bf8a0c9191bd938153eaf9e113755c35f88ac", "addr": "1H9jHs2V9Qbt1CmohUm2xGn58C3ukRSG9D" }, { "type": 0, "spent": true, "value": 940000, "spending_outpoints": [ { "tx_index": 8253179392378170, "n": 26 } ], "n": 1, "tx_index": 2088656368381968, "script": "76a914e9f94f48940491485c98b083f099fc2c47563ac288ac", "addr": "1NL973DXxArQjabNaMjh1FrrbBBmcUgndy" } ], "result": -270000, "balance": 0 }',
			),
			array(
				BlockStream_Info_API_Transaction_Adapter::class,
				'array',
				'{ "txid": "44032b210862cb2bdf549ac95c7d1a40e7fc2f81b42948047f818029a7f95c3b", "version": 1, "locktime": 689297, "vin": [ { "txid": "17d94f867484ca3458872459454966d0c7620ff52aa48a33c03db73c16cfeabd", "vout": 1, "prevout": { "scriptpubkey": "a914c15f1ad5162b35d8ddb3cf46009326d36252237187", "scriptpubkey_asm": "OP_HASH160 OP_PUSHBYTES_20 c15f1ad5162b35d8ddb3cf46009326d362522371 OP_EQUAL", "scriptpubkey_type": "p2sh", "scriptpubkey_address": "3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum", "value": 270000 }, "scriptsig": "2200204db0a225378ec24fde1ebf5f8f423893448aa9d1fae079b5d741f07795c9a798", "scriptsig_asm": "OP_PUSHBYTES_34 00204db0a225378ec24fde1ebf5f8f423893448aa9d1fae079b5d741f07795c9a798", "witness": [ "", "304402201874a90b1b3bba93065e36fbc0ef0917e54b3c07eff8629b09ef2a05b8fe5108022050cc104ddc0f81b9da79598e555d5d697be5421924cb557c55711713a978889d01", "3044022074fb0a4fefbac30331fb6e173dbd1d3f08fae124060ff0804f55d7991aa5b5bf022061e02aecf3f91468719ced6e0c126e87edab615ebbda0579eca55f44c62c290501", "522103407b5f92f2b01aa32acc7fc73d1274d8e8333f92d2e0748c069223b6f49463db21032910f98af72cc3973d29f7597d566cd28804b16977c89a2cdbd38248f64059e0210235b564577d986dad59ac83db3715bfd8e2d64992ae4da93b0ec9c5a0bb8d821053ae" ], "is_coinbase": false, "sequence": 4294967295, "inner_redeemscript_asm": "OP_0 OP_PUSHBYTES_32 4db0a225378ec24fde1ebf5f8f423893448aa9d1fae079b5d741f07795c9a798", "inner_witnessscript_asm": "OP_PUSHNUM_2 OP_PUSHBYTES_33 03407b5f92f2b01aa32acc7fc73d1274d8e8333f92d2e0748c069223b6f49463db OP_PUSHBYTES_33 032910f98af72cc3973d29f7597d566cd28804b16977c89a2cdbd38248f64059e0 OP_PUSHBYTES_33 0235b564577d986dad59ac83db3715bfd8e2d64992ae4da93b0ec9c5a0bb8d8210 OP_PUSHNUM_3 OP_CHECKMULTISIG" }, { "txid": "55dc74f4e7867a0990fa63ce32b7ade7562b5ee7104b1fd26425f9b5f682e52d", "vout": 17, "prevout": { "scriptpubkey": "a914d68249e1c0a6fe0d2702477f561dc7cbd567e50987", "scriptpubkey_asm": "OP_HASH160 OP_PUSHBYTES_20 d68249e1c0a6fe0d2702477f561dc7cbd567e509 OP_EQUAL", "scriptpubkey_type": "p2sh", "scriptpubkey_address": "3MFEcCunCKtrmZ5qAVQsRo5icgd4iasZ1w", "value": 1092000 }, "scriptsig": "220020751208a9a3d43dcdcbf3f7f6990609c52af221519cab520d9d5234d1e67ac093", "scriptsig_asm": "OP_PUSHBYTES_34 0020751208a9a3d43dcdcbf3f7f6990609c52af221519cab520d9d5234d1e67ac093", "witness": [ "", "304402200f8ea1dfc0dc3a53bda3e38a5327687cfe26f3b24d6e56e7d5e47f2cb4824cd9022002a05317cf5a6444bb901a5376a1684fbacb77fd1c564ffde3880faab4fac96701", "304402204c833ee43ea8c44f008687ca64059dc5cd021fbff342954a0bc3c932b19b1a85022018c31d211770c965abb3ab075e6d31017c0dacb5fd2c0fe72c3411216c298d4b01", "522103908cfe604e96160c5cd3065edd0bd55f2b274ca487d97efeffffa889141d760621035b327dad888bbac6055c62e6011d5fd928a2878383f49ba594ef980c84c5222f210236f6c921ffdca75285a138e67584b006faa7ef5d117f5113b7796371cbb2a5d153ae" ], "is_coinbase": false, "sequence": 4294967295, "inner_redeemscript_asm": "OP_0 OP_PUSHBYTES_32 751208a9a3d43dcdcbf3f7f6990609c52af221519cab520d9d5234d1e67ac093", "inner_witnessscript_asm": "OP_PUSHNUM_2 OP_PUSHBYTES_33 03908cfe604e96160c5cd3065edd0bd55f2b274ca487d97efeffffa889141d7606 OP_PUSHBYTES_33 035b327dad888bbac6055c62e6011d5fd928a2878383f49ba594ef980c84c5222f OP_PUSHBYTES_33 0236f6c921ffdca75285a138e67584b006faa7ef5d117f5113b7796371cbb2a5d1 OP_PUSHNUM_3 OP_CHECKMULTISIG" } ], "vout": [ { "scriptpubkey": "76a914b128a48bf8a0c9191bd938153eaf9e113755c35f88ac", "scriptpubkey_asm": "OP_DUP OP_HASH160 OP_PUSHBYTES_20 b128a48bf8a0c9191bd938153eaf9e113755c35f OP_EQUALVERIFY OP_CHECKSIG", "scriptpubkey_type": "p2pkh", "scriptpubkey_address": "1H9jHs2V9Qbt1CmohUm2xGn58C3ukRSG9D", "value": 396122 }, { "scriptpubkey": "76a914e9f94f48940491485c98b083f099fc2c47563ac288ac", "scriptpubkey_asm": "OP_DUP OP_HASH160 OP_PUSHBYTES_20 e9f94f48940491485c98b083f099fc2c47563ac2 OP_EQUALVERIFY OP_CHECKSIG", "scriptpubkey_type": "p2pkh", "scriptpubkey_address": "1NL973DXxArQjabNaMjh1FrrbBBmcUgndy", "value": 940000 } ], "size": 736, "weight": 1426, "fee": 25878, "status": { "confirmed": true, "block_height": 689299, "block_hash": "00000000000000000007823b8af61ec7c1e0c8508253e7b2be72c04972dfc7e3", "block_time": 1625135102 } }',
			),
		);
	}

	/**
	 * A transaction received to `3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum`.
	 *
	 * @dataProvider all_adapters_match_data_provider
	 *
	 * @param string              $adapter_class The `Transaction_Interface` adapter for the API.
	 * @param class-string|string $transaction_class The class (or "array") to map the `$json` to.
	 * @param string              $json HTTP API response JSON.
	 */
	public function test_all_adapters_match( string $adapter_class, string $transaction_class, string $json ): void {

		if ( WP_Mock::usingPatchwork() ) {
			$this->markTestSkipped( 'This test fails when Patchwork is enabled – jsonmapper fails.' );
		}

		if ( 'array' === $transaction_class ) {
			$transaction = json_decode( $json, true );
		} elseif ( class_exists( $transaction_class ) ) {
			$factory_registry = new FactoryRegistry();
			$mapper           = JsonMapperBuilder::new()->withPropertyMapper( new PropertyMapper( $factory_registry ) )->withAttributesMiddleware()->withDocBlockAnnotationsMiddleware()->withTypedPropertiesMiddleware()->withNamespaceResolverMiddleware()->withObjectConstructorMiddleware( $factory_registry )->build();
			$mapper->push( new CaseConversion( TextNotation::UNDERSCORE(), TextNotation::CAMEL_CASE() ) );
			$mapper->push( new AssociativeArrayMiddleware() );
			$transaction = $mapper->mapToClassFromString( $json, $transaction_class );
		} else {
			$this->fail();
		}

		/** @var Blockchain_Info_Api_Transaction_Adapter|BlockStream_Info_API_Transaction_Adapter $sut */
		$sut = new $adapter_class();
		/** @var Transaction_Interface $result */
		$result = $sut->adapt( $transaction );

		$this->assertEquals( '44032b210862cb2bdf549ac95c7d1a40e7fc2f81b42948047f818029a7f95c3b', $result->get_txid() );
		$this->assertEquals( 689299, $result->get_block_height() );
		$this->assertEquals( new DateTimeImmutable()->setDate( 2021, 07, 01 )->setTime( 10, 25, 02 ), $result->get_block_time() );
		$this->assertEquals( 1, $result->get_version() );

		$this->assertCount( 2, $result->get_v_in() );
		$v_in = $result->get_v_in()[0];
		$this->assertEquals( 4294967295, $v_in->sequence );
		$this->assertEquals( '2200204db0a225378ec24fde1ebf5f8f423893448aa9d1fae079b5d741f07795c9a798', $v_in->scriptsig );
		$this->assertEquals( '3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum', $v_in->address );
		$this->assertEquals( 'a914c15f1ad5162b35d8ddb3cf46009326d36252237187', $v_in->prevout_scriptpubkey );
		$this->assertEquals( Money::of( 0.0027, 'BTC' ), $v_in->value );
		$this->assertEquals( 1, $v_in->prev_out_n );

		$this->assertCount( 2, $result->get_v_out() );
		$v_out = $result->get_v_out()[0];
		$this->assertEquals( Money::of( 0.00396122, 'BTC' ), $v_out->value );
		$this->assertEquals( '1H9jHs2V9Qbt1CmohUm2xGn58C3ukRSG9D', $v_out->scriptpubkey_address );
	}
}
