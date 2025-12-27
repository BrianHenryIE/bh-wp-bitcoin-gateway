<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

readonly class Check_Assigned_Addresses_For_Transactions_Result {
	public function __construct(
		public int $count,
	) {
	}
}
