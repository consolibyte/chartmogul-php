<?php 

namespace ChartMogul;
 
class Import 
{
	const BASE = 'https://api.chartmogul.com/v1/import/';

	protected $_access_token;
	protected $_secret_key;

	protected $_data_source_uuid;

	protected $_last_error;

	protected $_last_request;
	protected $_last_response;

	public function __construct($access_token, $secret_key, $data_source_uuid = null)
	{
		$this->_access_token = $access_token;
		$this->_secret_key = $secret_key;

		$this->_data_source_uuid = $data_source_uuid;
	}

	public function ping()
	{
		/*
		curl -X GET "https://api.chartmogul.com/v1/ping" \
     -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY
		 */
		
		$out = $this->_request('../ping');

		return $this->_handleErrors($out);
	}

	protected function _request($endpoint, $payload = null, $force_method_to = null)
	{
		$Http = new Util\Http(self::BASE . $endpoint);

		$Http->setRawBody(json_encode($payload));

		$Http->setAuth($this->_access_token, $this->_secret_key);
		
		$Http->verifyPeer(false);
		$Http->verifyHost(false);

		$Http->setHeaders(array(
			'Content-Type: application/json', 
			));

		if ($force_method_to == 'PATCH')
		{
			$out = $Http->PATCH();
		}
		else if ($payload)
		{
			$out = $Http->POST();
		}
		else
		{
			$out = $Http->GET();
		}

		$this->_last_request = $Http->lastRequest();
		$this->_last_response = $Http->lastResponse();

		return json_decode($out);
	}

	public function setDataSource($uuid)
	{
		$this->_data_source_uuid = $uuid;
	}

	public function listDataSources()
	{
		/*
		https://api.chartmogul.com/v1/import/data_sources
		 */
		
		$out = $this->_request('data_sources');

		if ($this->_handleErrors($out))
		{
			return $out->data_sources;
		}

		return false;
	}

	public function dataSource($name)
	{
		/*
		# 1. Create a Data Source
curl -X POST "https://api.chartmogul.com/v1/import/data_sources" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{ 
		  "name": "In-house billing"
		 }'
		 */
		
		$out = $this->_request('data_sources', array(
			'name' => $name, 
			));

		return $this->_handleErrors($out);
	}

	protected function _pushError($err)
	{
		$this->_last_error[] = $err;
	}

	protected function _handleErrors($out)
	{
		if (!empty($out->errors))
		{
			$this->_last_error = $out->errors;

			return false;
		}

		if (!empty($out->error))
		{
			$this->_pushError($out->error);

			return false;
		}

		if (!empty($out->code) and 
			$out->code == ChartMogul::ERR_AUTH)
		{
			$this->_pushError($out->code . ': ' . $out->message);

			return false;
		}

		return $out;
	}

	public function lastRequest()
	{
		return $this->_last_request;
	}

	public function lastResponse()
	{
		return $this->_last_response;
	}

	public function lastErrors()
	{
		return $this->_last_error;
	}

	public function customer($customer)
	{
		/*
		# 2. Create Customers
curl -X POST "https://api.chartmogul.com/v1/import/customers" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{
		  "data_source_uuid": "ds_fef05d54-47b4-431b-aed2-eb6b9e545430",
		  "external_id": "cus_0001",
		  "name": "Adam Smith",
		  "email": "adam@smith.com",
		  "country": "US",
		  "city": "New York"
		 }'
		 */
		
		if (empty($customer['data_source_uuid']) and 
			$this->_data_source_uuid)
		{
			$customer['data_source_uuid'] = $this->_data_source_uuid;
		}

		$out = $this->_request('customers', $customer);

		return $this->_handleErrors($out);
	}

	public function plan($plan)
	{
		/*
		# 3. Create Subscription Plans
curl -X POST "https://api.chartmogul.com/v1/import/plans" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{ 
		   "data_source_uuid": "ds_fef05d54-47b4-431b-aed2-eb6b9e545430",
		   "name": "Bronze Plan",
		   "interval_count": 1,
		   "interval_unit": "month",
		   "external_id": "plan_0001"
		 }'
		 */
		
		if (empty($plan['data_source_uuid']) and 
			$this->_data_source_uuid)
		{
			$plan['data_source_uuid'] = $this->_data_source_uuid;
		}

		$out = $this->_request('plans', $plan);

		return $this->_handleErrors($out);
	}

	public function invoices($invoices)
	{
		$first = $invoices[0];

		$out = $this->_request('customers/' . $first['customer_uuid'] . '/invoices', array( 'invoices' => $invoices ));

		return $this->_handleErrors($out);
	}

	public function invoice($invoice)
	{
		$resp = $this->invoices(array( $invoice ));

		if ($this->_handleErrors($resp) and 
			!empty($resp->invoices))
		{
			return $this->_handleErrors($resp->invoices[0]);
		}
		else if ($this->_handleErrors($resp))
		{
			return false;
		}

		return false;

		/*
		# 4. Send Invoices
curl -X POST "https://api.chartmogul.com/v1/import/customers/cus_f466e33d-ff2b-4a11-8f85-417eb02157a7/invoices" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{
	 "invoices":[
	   {
		  "external_id": "INV0001",
		  "date": "2015-11-01 00:00:00",
		  "currency": "USD",
		  "due_date": "2015-11-15 00:00:00",
		  "line_items": [
			{
			  "type": "subscription",
			  "subscription_external_id": "sub_0001",
			  "plan_uuid":"pl_eed05d54-75b4-431b-adb2-eb6b9e543206",
			  "service_period_start": "2015-11-01 00:00:00",
			  "service_period_end": "2015-12-01 00:00:00",
			  "amount_in_cents": 5000,
			  "quantity": 1,
			  "discount_code": "PSO86",
			  "discount_amount_in_cents": 1000,
			  "tax_amount_in_cents": 900
			}
		  ],
		  "transactions": [
			{
			  "date": "2015-11-05 00:14:23",
			  "type": "payment",
			  "result": "successful"
			}
		  ]   
	   },
	   {
		  "external_id": "INV0002",
		  "date": "2015-12-01 00:00:00",
		  "currency": "USD",
		  "due_date": "2015-12-15 00:00:00",
		  "line_items": [
			{
			  "type": "subscription",
			  "subscription_external_id": "sub_0001",
			  "plan_uuid":"pl_eed05d54-75b4-431b-adb2-eb6b9e543206",
			  "service_period_start": "2015-12-01 00:00:00",
			  "service_period_end": "2016-01-01 00:00:00",
			  "amount_in_cents": 5000,
			  "quantity": 1,
			  "discount_code": "PSO86",
			  "discount_amount_in_cents": 1000,
			  "tax_amount_in_cents": 900
			}
		  ],
		  "transactions": [
			{
			  "date": "2015-12-05 07:54:02",
			  "type": "payment",
			  "result": "successful"
			}
		  ]   
	   }
	 ]
	 }'
		 */
		
		// PRORATED
		/*
		# Example of a prorated charge for an additional subscription
curl -X POST "https://api.chartmogul.com/v1/import/customers/cus_f466e33d-ff2b-4a11-8f85-417eb02157a7/invoices" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{
	 "invoices":[
	   {
		  "external_id": "INV0004",
		  "date": "2016-03-16 00:00:00",
		  "currency": "USD",
		  "line_items": [
			{
			  "type": "subscription",
			  "subscription_external_id": "sub_0001",
			  "plan_uuid":"pl_eed05d54-75b4-431b-adb2-eb6b9e543206",
			  "prorated": true,
			  "service_period_start": "2016-03-16 12:00:00",
			  "service_period_end": "2016-04-01 00:00:00",
			  "amount_in_cents": 2500,
			  "quantity": 1
			}
		  ],
		  "transactions": [
			{
			  "date": "2016-03-17 10:00:00",
			  "type": "payment",
			  "result": "successful"
			}
		  ]   
		}
	   ]
}'
		 */
	}

	public function cancel($sub_uuid, $when)
	{
		/*
		# When a Subscription is cancelled
curl -X PATCH "https://api.chartmogul.com/v1/import/subscriptions/sub_e6bc5407-e258-4de0-bb43-61faaf062035" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json" \
	 -d '{ 
		  "cancelled_at": "2016-01-15 00:00:00"
		}'
		 */
		
		$out = $this->_request('subscriptions/' . $sub_uuid, array(
			'cancelled_at' => date('c', strtotime($when))
			), 'PATCH');

		return $this->_handleErrors($out);
	}

	public function transaction($transaction)
	{
		/*
		curl -X POST "https://api.chartmogul.com/v1/import/invoices/inv_565c73b2-85b9-49c9-a25e-2b7df6a677c9/transactions" \
	 -u YOUR_ACCOUNT_TOKEN:YOUR_SECRET_KEY \
	 -H "Content-Type: application/json"
	 -d '{
			"type": "refund",
			"date": "2015-12-25 18:10:00",
			"result": "successful"
		 }'
		 */
		
		if (empty($transaction['invoice_uuid']))
		{
			$this->_pushError('You must specify an invoice_uuid.');
			return false;
		}

		$out = $this->_request('invoices/' . $transaction['invoice_uuid']. '/transactions', $transaction);

		return $this->_handleErrors($out);
	}


}