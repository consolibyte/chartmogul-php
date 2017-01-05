<?php

namespace ChartMogul;

class ChartMogul
{
	const ERR_AUTH = 401;
	const ERR_EXPIRED = 403;

	const TRANSACTION_TYPE_PAYMENT = 'payment';
	const TRANSACTION_TYPE_REFUND = 'refund';

	const TRANSACTION_RESULT_SUCCESSFUL = 'successful';
	const TRANSACTION_RESULT_FAILED = 'failed';
}