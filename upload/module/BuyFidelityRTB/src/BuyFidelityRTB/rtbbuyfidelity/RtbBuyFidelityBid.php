<?php
/**
 * CDNPAL NGINAD Project
 *
 * @link http://www.nginad.com
 * @copyright Copyright (c) 2013-2015 CDNPAL Ltd. All Rights Reserved
 * @license GPLv3
 */

namespace rtbbuyfidelity;
use \Exception;

abstract class RtbBuyFidelityBid extends \rtbbuy\RtbBuyBid {

	public $rtb_base_url = "http://rtb.demandpartner.com";

	public $had_bid_response = false;

	protected $rtb_provider = "none";

	// will be used for stats
	public $rtb_seat_id = null;
	
	public $response_seat_id = null;
	
	
	public $RtbBidRequest;
	public $RtbBidResponse;
	
	/*
	 * an array of RtbBidResponse objects
	 */

	public $bid_responses = array();

	private $RtbNoBidResponse = array(
	        "requestId"=>"",
			"bid"=>""
	);

	/*
	 * A List of AdCampaignBanner ORM objects that matched all the business rules from the incoming request
	*/
	private $AdCampaignBanner_Match_List = array();

	private $no_bid_reason;
	
	// CONFIG

	public $config;

	public $is_local_request	= false;

	public function __construct($config = null, $rtb_seat_id = null, $response_seat_id = null) {
		
		$this->rtb_seat_id 		= $rtb_seat_id !== null ? $rtb_seat_id : $this->rtb_provider;
		$this->response_seat_id = $response_seat_id !== null ? $response_seat_id : 'na';
		$this->config 			= $config;
	}

	private function generate_transaction_id() {

		return uniqid("cdnp" . "." . $this->rtb_provider, true);
	}


	function send_bid_response() {

	    $log_header = "\n----------------------------------------------------------------\n";
	    $log_header.= date('m-d-Y H:i:s') . " ------- NEW BID RESPONSE " . $this->rtb_provider . " -------\n";
	    $log_header.= "----------------------------------------------------------------\n";

	    \rtbbuyfidelity\RtbBuyFidelityLogger::get_instance()->log[] = $log_header;

			header("Content-type: application/json");
		    $bid_response = $this->bid_responses;
			echo $bid_response;
			\rtbbuyfidelity\RtbBuyFidelityLogger::get_instance()->log[] = $bid_response;
			\rtbbuyfidelity\RtbBuyFidelityLogger::get_instance()->min_log[] = $bid_response;
	}
	
	public function build_outgoing_bid_response() {

		// transform to JSON
		$this->bid_responses = \buyrtbfidelity\encoders\openrtb\RtbBidResponseJsonEncoder::execute($this->RtbBidResponse);
	}

	private function get_effective_ad_tag(&$AdCampaignBanner, $tld) {

			/*
			 * This is an ad tag somebody copy pasted into the RTB Manager
			 * which is not using our Revive ad server. Lets send back
			 * an on the fly iframe ad tag to our delivery mechanism
			 * which will send back the client's Javascript or an IFrame
			 * and count the ad impression.
			 */

			$delivery_adtag_js = $this->config['delivery']['adtag'];
			
			$classname = $this->random_classname();

			$winning_bid_auction_param = "";
			
			$cache_buster = time();

			if ($this->rtb_provider != "BuyLoopbackPartner"):
				$winning_bid_auction_param = "&winbid=\${AUCTION_PRICE}";
			endif;
			
			$effective_tag = "<script type='text/javascript' src='" . $delivery_adtag_js . "?zoneid=" . $AdCampaignBanner->AdCampaignBannerID . "&buyerid=" . $this->rtb_seat_id . "&height=" . $AdCampaignBanner->Height . "&width=" . $AdCampaignBanner->Width . "&tld=" . $tld . "&clktrc={NGINCLKTRK}" . $winning_bid_auction_param . "&ui=" . $this->user_ip_hash . "&cb=" . $cache_buster . "'></script>";
			
			// return rawurlencode($effective_tag);
			
			return $effective_tag;
	}
	
	private function get_banner_notice_url(&$AdCampaignBanner, $tld, $request_id, $price) {
	
		$delivery_adtag = $this->config['delivery']['url'];
			
		$classname = $this->random_classname();
	
		$vendor = "fidelity";
		
		$winning_bid_auction_param = "";
			
		$cache_buster = time();
	
		if ($this->rtb_provider != "BuyLoopbackPartner"):
			$winning_bid_auction_param = "&winbid=\${AUCTION_PRICE}";
		endif;
	
		$notice_tag = $delivery_adtag . "?nurl=true&zoneid=" . $AdCampaignBanner->AdCampaignBannerID . "&buyerid=" . $this->rtb_seat_id . "&orgprc=" . $price . "&request_id=" . $request_id . "&vendor=" . $vendor . "&tld=" . $tld . $winning_bid_auction_param . "&ui=" . $this->user_ip_hash . "&cb=" . $cache_buster;
	
		return $notice_tag;
	}
	
	private function get_video_notice_url(&$AdCampaignBanner, $tld) {
	
		$delivery_adtag = $this->config['delivery']['url'];
			
		$classname = $this->random_classname();
	
		$winning_bid_auction_param = "";
			
		$cache_buster = time();
	
		if ($this->rtb_provider != "BuyLoopbackPartner"):
			$winning_bid_auction_param = "&winbid=\${AUCTION_PRICE}";
		endif;

		$notice_tag = $delivery_adtag . "?video=vast&zoneid=" . $AdCampaignBanner->AdCampaignBannerID . "&buyerid=" . $this->rtb_seat_id . "&tld=" . $tld . "&clktrc={NGINCLKTRK}" . $winning_bid_auction_param . "&ui=" . $this->user_ip_hash . "&cb=" . $cache_buster;
	
		return $notice_tag;
	}

	private function random_classname()
	{
			$random_classname = $this->rand_letter();
			$random_classname.= rand(100, 999);
			$random_classname.= $this->rand_letter();
			$random_classname.= rand(100, 999);

			return $random_classname;
	}

	private function rand_letter()
	{
		$int = rand(0,24);
		$a_z = "abcdefghijklmnopqrstuvwxyz";
		$rand_letter = $a_z[$int];
		return $rand_letter;
	}

	public function process_business_logic() {

	    $this->AdCampaignBanner_Match_List = \rtbbuyfidelity\RtbBuyFidelityWorkflow::get_instance()->process_business_rules_workflow($this->config, $this->rtb_seat_id, $this->no_bid_reason, $this->RtbBidRequest);
	}

	public function parse_incoming_request($raw_post = null) {

		$OpenRTBParser = new \buyrtbfidelity\parsers\openrtb\OpenRTBParser();
		try {
			$this->RtbBidRequest = $OpenRTBParser->parse_request($this->config, $this->is_local_request, $raw_post);
		} catch (Exception $e) {
			$logger =\rtbbuyfidelity\RtbBuyFidelityLogger::get_instance();
			if ($logger->setting_log === true):
				$logger->log[] = "Failed: " . "RTB Request Parse ERROR :: " . $e->getMessage();
			endif;
			$this->no_bid_reason = NOBID_INVALID_REQUEST;
			return false;
		}
		return true;
	}
	
	/*
	 * FIDELITY MOD:
	* One bid per request only. Multiple bid/seat responses won�t be accepted.
	*/
	public function fidelity_dedupe_bid_response() {
	
		$high_bid = null;
	
		$AdCampaignBanner_Match_List = array();
	
		foreach ($this->AdCampaignBanner_Match_List as $user_id => $AdCampaignBannerObjList):
				
			$RtbBidResponseSeatBid	= new \model\openrtb\RtbBidResponseSeatBid();
		
			foreach ($AdCampaignBannerObjList as $AdCampaignBannerObj):
			
				$bid_imp_id 				= $AdCampaignBannerObj["impid"];
				$AdCampaignBanner 			= $AdCampaignBannerObj["AdCampaignBanner"];
					
				/*
				 * Poll the highest bid and set it as the only bid
				*/
				if ($high_bid === null || floatval($high_bid) < floatval($AdCampaignBanner->BidAmount)):
					
					$high_bid = $AdCampaignBanner->BidAmount;
					$_AdCampaignBannerObjList = array();
					$_AdCampaignBannerObjList[] = $AdCampaignBannerObj;
					$AdCampaignBanner_Match_List = array();
					$AdCampaignBanner_Match_List[$user_id] = $_AdCampaignBannerObjList;
						
				endif;
			
			endforeach;
		
		endforeach;
	
		$this->AdCampaignBanner_Match_List = $AdCampaignBanner_Match_List;
		
	}

	public function convert_ads_to_bid_response() {
		
		// init the bid response object
		$RtbBidResponse	= new \model\openrtb\RtbBidResponse();
		
		/*
		 * get TLD of the site url or page url for the
		* ad tag in case it's needed for the delivery module
		*/
		
		$tld = "not_available";

		if ($this->RtbBidRequest != null):
		
			$parse = null;
		
			if (isset($this->RtbBidRequest->RtbBidRequestSite->domain)):
				$parse = parse_url($this->RtbBidRequest->RtbBidRequestSite->domain);
			elseif (isset($this->RtbBidRequest->RtbBidRequestApp->domain)):
				$parse = parse_url($this->RtbBidRequest->RtbBidRequestApp->domain);
			endif;
			if (!empty($parse) && isset($parse['host'])):
				$tld = $parse['host'];
			else:
				if (isset($this->RtbBidRequest->RtbBidRequestSite->page)):
					$parse = parse_url($this->RtbBidRequest->RtbBidRequestSite->page);
				elseif (isset($this->RtbBidRequest->RtbBidRequestApp->page)):
					$parse = parse_url($this->RtbBidRequest->RtbBidRequestApp->page);
				endif;
				if (!empty($parse) && isset($parse['host'])):
					$tld = $parse['host'];
				endif;
			endif;


			$this->user_ip_hash = md5($this->RtbBidRequest->RtbBidRequestDevice->ip);
			
			$RtbBidResponse->id = $this->RtbBidRequest->id;
			
		endif;
		
		$RtbBidResponse->RtbBidResponseSeatBidList = array();
		
		$currency = null;

		foreach ($this->AdCampaignBanner_Match_List as $user_id => $AdCampaignBannerObjList):
		
			$RtbBidResponseSeatBid	= new \model\openrtb\RtbBidResponseSeatBid();
				
			foreach ($AdCampaignBannerObjList as $AdCampaignBannerObj):
				
				$bid_imp_id 				= $AdCampaignBannerObj["impid"];
				$AdCampaignBanner 			= $AdCampaignBannerObj["AdCampaignBanner"];
					
				if (isset($AdCampaignBannerObj["currency"]) && $currency == null):
					$currency = $AdCampaignBannerObj["currency"];
				endif;
					
				$RtbBidResponseBid	= new \model\openrtb\RtbBidResponseBid();
					
				$RtbBidResponseBid->id			= $this->generate_transaction_id();
				$RtbBidResponseBid->adid		= $RtbBidResponseBid->id;
				$RtbBidResponseBid->impid		= $bid_imp_id;
				$RtbBidResponseBid->price		= $AdCampaignBanner->BidAmount;
		
				if ($AdCampaignBanner->ImpressionType == 'video'):
					$RtbBidResponseBid->nurl 	= $this->get_video_notice_url($AdCampaignBanner, $tld);
				else:
					$RtbBidResponseBid->adm		= $this->get_effective_ad_tag($AdCampaignBanner, $tld);
					$RtbBidResponseBid->nurl 	= $this->get_banner_notice_url($AdCampaignBanner, $tld, $this->RtbBidRequest->id, $AdCampaignBanner->BidAmount);
				endif;
				
				$RtbBidResponseBid->adomain[] 	= $AdCampaignBanner->LandingPageTLD;
				$RtbBidResponseBid->cid	 		= "nginad_" . $AdCampaignBanner->AdCampaignID;
				$RtbBidResponseBid->crid	 	= "nginad_" . $AdCampaignBanner->AdCampaignBannerID;
				$this->had_bid_response = true;
				
				$RtbBidResponseSeatBid->RtbBidResponseBidList[] = $RtbBidResponseBid;
					
			endforeach;
			
			$RtbBidResponseSeatBid->seat						= $user_id;
			$RtbBidResponse->RtbBidResponseSeatBidList[] 		= $RtbBidResponseSeatBid;
			
		endforeach;
			
		if (isset($AdCampaignBannerObj["currency"]) && $currency == null):
			$RtbBidResponse->cur				= $currency;
		else:
			$RtbBidResponse->cur				= $this->config['settings']['rtb']['auction_currency'];
		endif;
		
		if (!count($RtbBidResponse->RtbBidResponseSeatBidList)):
				
			// implement Rubicon Project's empty bid with $0.00 CPM here
			// also add the rejection code
			$RtbBidResponseSeatBid								= new \model\openrtb\RtbBidResponseSeatBid();
			$RtbBidResponseBid									= new \model\openrtb\RtbBidResponseBid();
			$RtbBidResponseBid->price 							= 0;
			$RtbBidResponseSeatBid->RtbBidResponseBidList[] 	= $RtbBidResponseBid;
			$RtbBidResponse->RtbBidResponseSeatBidList[] 		= $RtbBidResponseSeatBid;
			unset($RtbBidResponse->id);
			unset($RtbBidResponse->cur);
			if ($this->no_bid_reason != null):
				$RtbBidResponse->nbr 							= $this->no_bid_reason;
			endif;
			
		endif;
		
		$this->RtbBidResponse = $RtbBidResponse;
	
	}
	
}
