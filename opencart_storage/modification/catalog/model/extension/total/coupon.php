<?php
class ModelExtensionTotalCoupon extends Model {
	public function getCoupon($code) {
		$status = true;

		$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");

	$seller_coupon = $this->db->query("SELECT pvc.seller_id,co.code,co.* FROM `" . DB_PREFIX . "coupon` co Inner JOIN `" . DB_PREFIX . "purpletree_vendor_coupons` pvc ON (co.coupon_id = pvc.coupon_id) WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1' AND pvc.seller_id != '0'");
	if ($seller_coupon->num_rows>0) {
		$seller_id = $seller_coupon->row['seller_id'];
		$seller_coupon1 = $seller_coupon->row['code'];
	}else{
		$seller_id = 0;
		$seller_coupon1 = '';
	}			
		

		if ($coupon_query->num_rows) {

			if(!empty($seller_coupon1)){
				if ($coupon_query->row['total'] > $this->cart->getSellerSubTotal($seller_id)) {
					$status = false;
				}	
		
			if ($coupon_query->row['total'] > $this->cart->getSubTotal()) {
				$status = false;
			}

		}else{
		if ($coupon_query->row['total'] > $this->cart->getSubTotal()) {
				$status = false;
			}
		}
	if(!empty($seller_coupon1)){
		$seller_coupon_total = $this->getSellerTotalCouponHistoriesByCoupon($code);
			if ($coupon_query->row['uses_total'] > 0 && ($seller_coupon_total >= $coupon_query->row['uses_total'])) {
				 
			$status = false;
		}
	}  else { 
		

			$coupon_total = $this->getTotalCouponHistoriesByCoupon($code);

			if ($coupon_query->row['uses_total'] > 0 && ($coupon_total >= $coupon_query->row['uses_total'])) {
				$status = false;
			}


		}
		
			if ($coupon_query->row['logged'] && !$this->customer->getId()) {
				$status = false;
			}

			if ($this->customer->getId()) {
				$customer_total = $this->getTotalCouponHistoriesByCustomerId($code, $this->customer->getId());
				
				if ($coupon_query->row['uses_customer'] > 0 && ($customer_total >= $coupon_query->row['uses_customer'])) {
					$status = false;
				}
			}

			// Products
			$coupon_product_data = array();

			$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_product_query->rows as $product) {
				$coupon_product_data[] = $product['product_id'];
			}

			// Categories
			$coupon_category_data = array();

			$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_category_query->rows as $category) {
				$coupon_category_data[] = $category['category_id'];
			}

			$product_data = array();

			
		if ($coupon_product_data || $coupon_category_data || !empty($seller_coupon1)) {
				if(!empty($seller_coupon1)){
		$continueee = false;
		foreach ($this->cart->getsellerProducts($seller_id) as $product) {
			if (in_array($product['product_id'], $coupon_product_data)) {
					$product_data[] = $product['product_id'];
					$continueee = true;
					continue;
				}elseif(empty($coupon_product_data)&& ($product['product_id'] > 0)){
				 $continueee = true;
				}
				foreach ($coupon_category_data as $category_id) {
					$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

					if ($coupon_category_query->row['total']) {
						$product_data[] = $product['product_id'];
						$continueee = true;
						continue;
					}
				}
				if(!$continueee) {
					$status = false;
				}
		}
	} else { 
		
				foreach ($this->cart->getProducts() as $product) {
					if (in_array($product['product_id'], $coupon_product_data)) {
						$product_data[] = $product['product_id'];

						continue;
					}

					foreach ($coupon_category_data as $category_id) {
						$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

						if ($coupon_category_query->row['total']) {
							$product_data[] = $product['product_id'];

							continue;
						}
					}
				}


				}
		
				
				if (!$product_data && ($continueee != 1)) {
		
					$status = false;
				}
			}
		} else {
			$status = false;
		}

		if ($status) {
			return array(
				'coupon_id'     => $coupon_query->row['coupon_id'],
				'code'          => $coupon_query->row['code'],
				'name'          => $coupon_query->row['name'],
				'type'          => $coupon_query->row['type'],
				'discount'      => $coupon_query->row['discount'],
				'shipping'      => $coupon_query->row['shipping'],
				'total'         => $coupon_query->row['total'],
				'product'       => $product_data,
				'date_start'    => $coupon_query->row['date_start'],
				'date_end'      => $coupon_query->row['date_end'],
				'uses_total'    => $coupon_query->row['uses_total'],
				'uses_customer' => $coupon_query->row['uses_customer'],
				'status'        => $coupon_query->row['status'],
				'date_added'    => $coupon_query->row['date_added']
			);
		}
	}

	public function getTotal($total) {
		if (isset($this->session->data['coupon'])) {

		$seller_idby_coupon = $this->db->query("SELECT pvc.seller_id,co.* FROM `" . DB_PREFIX . "coupon` co Inner JOIN `" . DB_PREFIX . "purpletree_vendor_coupons` pvc ON (co.coupon_id = pvc.coupon_id) WHERE code = '" . $this->db->escape($this->session->data['coupon']) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1' AND pvc.seller_id >= '1'");
		if($seller_idby_coupon->num_rows){
			$seller_id =  $seller_idby_coupon->row['seller_id'];
		}else{
			$seller_id = 0;
		}
		
			$this->load->language('extension/total/coupon', 'coupon');

			$coupon_info = $this->getCoupon($this->session->data['coupon']);

			if ($coupon_info) {
				$discount_total = 0;

				if (!$coupon_info['product']) {

		if ($seller_id!= 0) {
			$sub_total = $this->cart->getSellerSubTotal($seller_id);
		}else{
		
					$sub_total = $this->cart->getSubTotal();

		}
		
				} else {
					$sub_total = 0;

				if ($seller_id != 0) {
					foreach ($this->cart->getSellerProducts($seller_id) as $product) {
						if (in_array($product['product_id'], $coupon_info['product'])) {
							$sub_total += $product['total'];
						}
					}
				}else{
		

					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_info['product'])) {
							$sub_total += $product['total'];
						}
					}
				}


			}
		
				if ($coupon_info['type'] == 'F') {
					$coupon_info['discount'] = min($coupon_info['discount'], $sub_total);
				}


			  if ($seller_id != 0) {
			   foreach ($this->cart->getSellerProducts($seller_id) as $product) {
			   $discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				   } } else{
		
				foreach ($this->cart->getProducts() as $product) {
					$discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				}

			}
		

				if ($coupon_info['shipping'] && isset($this->session->data['shipping_method'])) {
					if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
						$tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

						foreach ($tax_rates as $tax_rate) {
							if ($tax_rate['type'] == 'P') {
								$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
							}
						}
					}

					$discount_total += $this->session->data['shipping_method']['cost'];
				}

				// If discount greater than total
				if ($discount_total > $total['total']) {
					$discount_total = $total['total'];
				}

				if ($discount_total > 0) {
					$total['totals'][] = array(
						'code'       => 'coupon',
						'title'      => sprintf($this->language->get('coupon')->get('text_coupon'), $this->session->data['coupon']),
						'value'      => -$discount_total,
						'sort_order' => $this->config->get('total_coupon_sort_order')
					);

					$total['total'] -= $discount_total;
				}
			}
		}
	}

	public function confirm($order_info, $order_total) {
		$code = '';

		$start = strpos($order_total['title'], '(') + 1;
		$end = strrpos($order_total['title'], ')');

		if ($start && $end) {
			$code = substr($order_total['title'], $start, $end - $start);
		}

		if ($code) {
			$status = true;
			
			$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $this->db->escape($code) . "' AND status = '1'");

			if ($coupon_query->num_rows) {
				$coupon_total = $this->getTotalCouponHistoriesByCoupon($code);
	
				if ($coupon_query->row['uses_total'] > 0 && ($coupon_total >= $coupon_query->row['uses_total'])) {
					$status = false;
				}
				
				if ($order_info['customer_id']) {
					$customer_total = $this->getTotalCouponHistoriesByCustomerId($code, $order_info['customer_id']);
					
					if ($coupon_query->row['uses_customer'] > 0 && ($customer_total >= $coupon_query->row['uses_customer'])) {
						$status = false;
					}
				}
			} else {
				$status = false;	
			}

			if ($status) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "coupon_history` SET coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "', order_id = '" . (int)$order_info['order_id'] . "', customer_id = '" . (int)$order_info['customer_id'] . "', amount = '" . (float)$order_total['value'] . "', date_added = NOW()");
			} else {
				return $this->config->get('config_fraud_status_id');
			}
		}
	}

	public function unconfirm($order_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "coupon_history` WHERE order_id = '" . (int)$order_id . "'");
	}
	
	public function getTotalCouponHistoriesByCoupon($coupon) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '" . $this->db->escape($coupon) . "'");	
		
		return $query->row['total'];
	}
	

				public function getSellerTotalCouponHistoriesByCoupon($coupon) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) LEFT JOIN `" . DB_PREFIX . "purpletree_vendor_coupons` pot ON (pot.coupon_id = ch.coupon_id) WHERE c.code = '" . $this->db->escape($coupon) . "'");	
		
		return $query->row['total'];
	}
			public function getsellercounponTotal($product_idd) {
		if (isset($this->session->data['coupon'])) {

		$seller_idby_coupon = $this->db->query("SELECT pvc.seller_id,co.* FROM `" . DB_PREFIX . "coupon` co Inner JOIN `" . DB_PREFIX . "purpletree_vendor_coupons` pvc ON (co.coupon_id = pvc.coupon_id) WHERE code = '" . $this->db->escape($this->session->data['coupon']) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1' AND pvc.seller_id >= '1'");
		if($seller_idby_coupon->num_rows){
			$seller_id =  $seller_idby_coupon->row['seller_id'];
		}else{
			$seller_id = 0;
		}
		
			$this->load->language('extension/total/coupon', 'coupon');

			$coupon_info = $this->getCoupon($this->session->data['coupon']);

			if ($coupon_info) {
				$discount_total = 0;

				if (!$coupon_info['product']) {

		if ($seller_id!= 0) {
			$sub_total = $this->cart->getSellerSubTotal($seller_id);
		}else{
		
					$sub_total = $this->cart->getSubTotal();

		}
		
				} else {
					$sub_total = 0;

				if ($seller_id != 0) {
					foreach ($this->cart->getSellerProducts($seller_id) as $product) {
						if($product_idd == $product['product_id']) {
							if (in_array($product['product_id'], $coupon_info['product'])) {
								$sub_total += $product['total'];
							}
						}
					}
				}else{
		

					foreach ($this->cart->getProducts() as $product) {
						if($product_idd == $product['product_id']) {
							if (in_array($product['product_id'], $coupon_info['product'])) {
								$sub_total += $product['total'];
							}
						}
					}
				}


			}
		
				if ($coupon_info['type'] == 'F') {
					$coupon_info['discount'] = min($coupon_info['discount'], $sub_total);
				}


			  if ($seller_id != 0) {
			   foreach ($this->cart->getSellerProducts($seller_id) as $product) {
				   if($product_idd == $product['product_id']) {
			   $discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									//$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				   }
			  }
				   } else{
		
				foreach ($this->cart->getProducts() as $product) {
					if($product_idd == $product['product_id']) {
					$discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									//$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				}
				}

			}
		

				if ($coupon_info['shipping'] && isset($this->session->data['shipping_method'])) {
					if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
						$tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

						foreach ($tax_rates as $tax_rate) {
							if ($tax_rate['type'] == 'P') {
								//$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
							}
						}
					}

					$discount_total += $this->session->data['shipping_method']['cost'];
				}

				// If discount greater than total
				//if ($discount_total > $total['total']) {
					//$discount_total = $total['total'];
				//}

				if ($discount_total > 0) {
					return $discount_total;
				}
			}
		}
	}
		
	public function getTotalCouponHistoriesByCustomerId($coupon, $customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '" . $this->db->escape($coupon) . "' AND ch.customer_id = '" . (int)$customer_id . "'");
		
		return $query->row['total'];
	}
}
