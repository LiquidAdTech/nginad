<?php
/**
 * CDNPAL NGINAD Project
 *
 * @link http://www.nginad.com
 * @copyright Copyright (c) 2013-2015 CDNPAL Ltd. All Rights Reserved
 * @license GPLv3
 */

namespace buyrtb\workflows\tasklets\video\adcampaignvideorestrictions;

class CheckMimeTypes {
	
	public static function execute(&$Logger, &$Workflow, \model\openrtb\RtbBidRequest &$RtbBidRequest, \model\openrtb\RtbBidRequestImp &$RtbBidRequestImp, &$AdCampaignBanner, &$AdCampaignVideoRestrictions) {
		
		$RtbBidRequestVideo = $RtbBidRequestImp->RtbBidRequestVideo;
		
		if (empty($AdCampaignVideoRestrictions->MimesCommaSeparated)):
			return true;
		endif;
		
		$mime_code_list = explode(',', $AdCampaignVideoRestrictions->MimesCommaSeparated);
		
		if (!count($mime_code_list)):
			return true;
		endif;
		
		// Validate that the value is an array
		if (!is_array($RtbBidRequestVideo->mimes)):
			if ($Logger->setting_log === true):
			$Logger->log[] = "Failed: " . "Check video mime type code :: EXPECTED: "
					. 'Array(),'
					. " GOT: " . $RtbBidRequestVideo->mimes;
			endif;
			return false;
		endif;

		$result = false;
		

		
		/*
		 * All codes in the publisher ad zone
		* for the publisher's video player settings
		* have to be included in the VAST video demand
		*/
		foreach($RtbBidRequestVideo->mimes as $mime_code_to_match):
		
			if (!in_array($mime_code_to_match, $mime_code_list)):
			
				$result = false;
				break;
					
			endif;
			
		endforeach;
		
		if ($result === false && $Logger->setting_log === true):
			$Logger->log[] = "Failed: " . "Check video mime type code :: EXPECTED: "
				. $AdCampaignVideoRestrictions->MimesCommaSeparated
				. " GOT: " . join(',', $RtbBidRequestVideo->mimes);
		endif;
		
		return false;
	}
}