<?php

/**
 * Form Submissions Extension for Contao Open Source CMS
 *
 * Copyright (c) 2018 Web ex Machina
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */

namespace WEM\Form;

use Exception;
use Contao\Input;
use Contao\Controller;
use Contao\RequestToken;

use WEM\Form\Model\Submission;
use WEM\Form\Model\Field;
use WEM\Form\Model\Log;

/**
 * Hooks functions
 */
class Hooks extends Controller
{
	/**
	 * Catch AJAX Requests
	 * @param  [Object] $objPage   [Page Model]
	 * @param  [Object] $objLayout [Layout Model]
	 * @param  [Object] $objPty    [Page Type Model]
	 */
	public function catchAjaxRequest($objPage, $objLayout, $objPty){
		if(Input::post("TL_AJAX") && "wem-contao-form-submissions" === Input::post("module")){
			try{
				if(null == Input::post("form"))
					throw new Exception("Aucun formulaire envoyé");

				$objForm = \FormModel::findByPk(Input::post("form"));
				if(!$objForm)
					throw new Exception("Formulaire invalide");

				switch(Input::post("action")){
					// Check if we must trigger the form storage functions
					case "checkForm":
						if(!$objForm->wemStoreSubmissions)
							$arrResponse = ["status"=>"success", "tobuild"=>0];
						else
							$arrResponse = ["status"=>"success", "tobuild"=>1];
					break;
					// In case the user doesn't finish to fill the form, we still store the data and the "not submit until the end" stuff
					case "abortSubmission":
						// Create the submission, with a special status
						$objSubmission = new Submission();
						$objSubmission->tstamp = time();
						$objSubmission->pid = $objForm->id;
						$objSubmission->createdAt = time();
						$objSubmission->status = "aborted";
						$objSubmission->ip = \Environment::get('ip');
						$objSubmission->token = md5(uniqid(mt_rand(), true));

						// Stop the function if an error occured in the Model save
						if(!$objSubmission->save())
							throw new Exception("Une erreur est survenue lors de la validation du formulaire");

						// Store the fields
						$j = 0;
						if(Input::post("fields") && !empty(Input::post("fields"))){
							foreach(Input::post("fields") as $field => $value){
								$objField = new Field();
								$objField->tstamp = time();
								$objField->pid = intval($objSubmission->id);
								$objField->field = $field;
								$objField->value = $value;

								if($objField->save())
									$j++;
							}
						}

						// Store the logs if there is any
						$i = 0;
						if(Input::post("logs") && !empty(Input::post("logs"))){
							foreach(Input::post("logs") as $log){
								$objLog = new Log();
								$objLog->tstamp = time();
								$objLog->pid = intval($objSubmission->id);
								$objLog->createdAt = $log["createdAt"];
								$objLog->type = $log["type"];
								$objLog->log = $log["log"];
								
								if($objLog->save())
									$i++;
							}
						}

						// And notify frontend of the success
						$arrResponse = ["status"=>"success", "msg"=>$j." champs et ".$i." logs ont été sauvegardés"];
					break;

					default:
						throw new Exception("Action inconnue");
				}
			}
			catch(Exception $e){
				$arrResponse = ["status"=>"error", "msg"=>$e->getMessage(), "method"=>__METHOD__];
			}

			// Send the answer
			$arrResponse["rt"] = RequestToken::get();
			echo json_encode($arrResponse);
			die;
		}

		// Load JS library
		$GLOBALS["TL_JAVASCRIPT"][] = 'system/modules/wem-contao-form-submissions/assets/js/functions.js';
	}

	/**
	 * Add one hidden input to the form for storage purpose
	 * @param  [Array] $arrFields [Original Form Fields]
	 * @param  [Integer] $intFormId [Form ID]
	 * @param  [Object] $objForm   [Form Row]
	 * @return [Array]            [Updated, or not Form Fields]
	 */
	public function addHiddenFields($arrFields, $intFormId, $objForm){
	    // Break if we don't have the option that interest us
	    if(!$objForm->wemStoreSubmissions)
	    	return $arrFields;

	    $objFormField = new \FormFieldModel();
	    $objFormField->pid = $intFormId;
	    $objFormField->tstamp = time();
	    $objFormField->sorting = 128128;
	    $objFormField->type = "hidden";
	    $objFormField->name = "wem_tracker_logs";

	    $arrFields[] = $objFormField;

	    return $arrFields;
	}

	/**
	 * Check and store the data from form submission
	 * @param  [Array] &$arrSubmitted [Values posted]
	 * @param  [Array] $arrLabels     [Form labels]
	 * @param  [Array] $arrFields     [Form fields]
	 * @param  [Object] $objForm       [Form object]
	 * @return [Array]                [Form fields updated, or not]
	 */
	public function storeFormLogs(&$arrSubmitted, $arrLabels, $arrFields, $objForm){
	    // Break if we don't have the option that interest us
	    if(!$objForm->wemStoreSubmissions)
	    	return;

	    // Create the submission first
	    $objSubmission = new Submission();
		$objSubmission->tstamp = time();
		$objSubmission->pid = $objForm->id;
		$objSubmission->createdAt = time();
		$objSubmission->status = "created";
		$objSubmission->ip = \Environment::get('ip');
		$objSubmission->token = md5(uniqid(mt_rand(), true));

		if(!$objSubmission->save())
			throw new Exception("Une erreur est survenue lors de la validation du formulaire");

		// Then, store the fields entered
		if($arrSubmitted){
			foreach($arrSubmitted as $field => $value){
				// Skip the "fake" field
				if("wem_tracker_logs" === $field)
					continue;

				$objField = new Field();
				$objField->tstamp = time();
				$objField->pid = intval($objSubmission->id);
				$objField->field = intval($arrFields[$field]->id);
				$objField->value = $value;
				$objField->save();
			}
		}

		// And finally, store the logs sent
		if($arrSubmitted["wem_tracker_logs"]){
			foreach(json_decode($arrSubmitted["wem_tracker_logs"]) as $log){
				$log = (array)$log;
				$objLog = new Log();
				$objLog->tstamp = time();
				$objLog->pid = intval($objSubmission->id);
				$objLog->createdAt = $log["createdAt"];
				$objLog->type = $log["type"];
				$objLog->log = $log["log"];
				$objLog->save();
			}
		}
	}
}