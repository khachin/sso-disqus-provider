<?php
	// SSO Disqus Account Provider
	// (C) 2014 Khachin.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	class sso_disqus extends SSO_ProviderBase
	{
		// See:
		// https://disqus.com/api/docs/auth/
		private static $fieldmap = array(
			"name" => array("title" => "Full Name", "desc" => "user's full name", "extra" => ""),
			"email" => array("title" => "Email Address", "desc" => "user's email address", "extra" => ""),
			"avatar" => array("title" => "Profile Photo URL", "desc" => "user's profile picture", "extra" => ""),
		);

		public function Init()
		{
			global $sso_settings;

			if (!isset($sso_settings["sso_disqus"]["client_id"]))  $sso_settings["sso_disqus"]["client_id"] = "";
			if (!isset($sso_settings["sso_disqus"]["client_secret"]))  $sso_settings["sso_disqus"]["client_secret"] = "";
			if (!isset($sso_settings["sso_disqus"]["enabled"]))  $sso_settings["sso_disqus"]["enabled"] = false;
			if (!isset($sso_settings["sso_disqus"]["email_bad_domains"]))  $sso_settings["sso_disqus"]["email_bad_domains"] = "";
			if (!isset($sso_settings["sso_disqus"]["iprestrict"]))  $sso_settings["sso_disqus"]["iprestrict"] = SSO_InitIPFields();

			foreach (self::$fieldmap as $key => $info)
			{
				if (!isset($sso_settings["sso_disqus"]["map_" . $key]) || !SSO_IsField($sso_settings["sso_disqus"]["map_" . $key]))  $sso_settings["sso_disqus"]["map_" . $key] = "";
			}
		}

		public function DisplayName()
		{
			return BB_Translate("Disqus");
		}

		public function DefaultOrder()
		{
			return 100;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "Disqus Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_disqus"]["enabled"])
				{
					$result["items"] = array(
						"Configure" => SSO_CreateConfigURL("config"),
						"Disable" => SSO_CreateConfigURL("disable")
					);
				}
				else
				{
					$result["items"] = array(
						"Enable" => SSO_CreateConfigURL("enable")
					);
				}
			}

			return $result;
		}

		public function Config()
		{
			global $sso_site_admin, $sso_settings, $sso_menuopts, $sso_select_fields, $sso_provider;

			if ($sso_site_admin && $sso_settings["sso_disqus"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					$_REQUEST["client_id"] = trim($_REQUEST["client_id"]);
					$_REQUEST["client_secret"] = trim($_REQUEST["client_secret"]);

					if ($_REQUEST["client_id"] == "")  BB_SetPageMessage("info", "The 'Disqus API Client ID' field is empty.");
					else if ($_REQUEST["client_secret"] == "")  BB_SetPageMessage("info", "The 'Disqus API Client Secret' field is empty.");

					$sso_settings["sso_disqus"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						$sso_settings["sso_disqus"]["client_id"] = $_REQUEST["client_id"];
						$sso_settings["sso_disqus"]["client_secret"] = $_REQUEST["client_secret"];

						foreach (self::$fieldmap as $key => $info)
						{
							$sso_settings["sso_disqus"]["map_" . $key] = (SSO_IsField($_REQUEST["map_" . $key]) ? $_REQUEST["map_" . $key] : "");
						}

						$sso_settings["sso_disqus"]["email_bad_domains"] = $_REQUEST["email_bad_domains"];

						if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
						else if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("config", array(), "info", $_REQUEST["bb_msg"] . "  " . BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
						else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Configure the %s provider.  Mapping additional fields that require extra permissions will significantly reduce the likelihood the user will sign in.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_disqus",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(
						array(
							"title" => "Disqus API Redirect URI",
							"type" => "static",
							"value" => BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_disqus_action=signin",
							"htmldesc" => "<br />When you <a href=\"https://code.disqus.com/apis/console/\" target=\"_blank\">create a Disqus APIs Project OAuth 2.0 token</a>, use the above URL for the 'Authorized Redirect URI' under the advanced settings.  OAuth 2.0 access can be set up under the 'API Access' tab of a Disqus APIs Project.  This provider will not work without a correct Redirect URI."
						),
						array(
							"title" => "Disqus API Client ID",
							"type" => "text",
							"name" => "client_id",
							"value" => BB_GetValue("client_id", $sso_settings["sso_disqus"]["client_id"]),
							"htmldesc" => "You get a Disqus API Client ID when you <a href=\"https://code.disqus.com/apis/console/\" target=\"_blank\">create a Disqus APIs Project OAuth 2.0 token</a>.  OAuth 2.0 access can be set up under the 'API Access' tab of a Disqus APIs Project.  This provider will not work without a Client ID."
						),
						array(
							"title" => "Disqus API Client Secret",
							"type" => "text",
							"name" => "client_secret",
							"value" => BB_GetValue("client_secret", $sso_settings["sso_disqus"]["client_secret"]),
							"htmldesc" => "You get a Disqus API Client Secret when you <a href=\"https://code.disqus.com/apis/console/\" target=\"_blank\">create a Disqus APIs Project OAuth 2.0 token</a>.  OAuth 2.0 access can be set up under the 'API Access' tab of a Disqus APIs Project.  This provider will not work without a Client Secret."
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				foreach (self::$fieldmap as $key => $info)
				{
					$contentopts["fields"][] = array(
						"title" => BB_Translate("Map %s", $info["title"]),
						"type" => "select",
						"name" => "map_" . $key,
						"options" => $sso_select_fields,
						"select" => BB_GetValue("map_" . $key, (string)$sso_settings["sso_disqus"]["map_" . $key]),
						"desc" => ($info["extra"] == "" ? BB_Translate("The field in the SSO system to map the %s to.%s", BB_Translate($info["desc"]), (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")) : BB_Translate("The field in the SSO system to map the %s to.  Mapping this field will request the '%s' permission from the user.%s", BB_Translate($info["desc"]), $info["extra"], (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")))
					);
				}

				$contentopts["fields"][] = array(
					"title" => "Email Domain Blacklist",
					"type" => "textarea",
					"height" => "300px",
					"name" => "email_bad_domains",
					"value" => BB_GetValue("email_bad_domains", $sso_settings["sso_disqus"]["email_bad_domains"]),
					"desc" => "A blacklist of email address domains that are not allowed to create accounts.  One per line.  Email Address must be mapped."
				);

				SSO_AppendIPFields($contentopts, $sso_settings["sso_disqus"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_disqus"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_disqus"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_disqus"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				$sso_settings["sso_disqus"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
		}

		public function IsEnabled()
		{
			global $sso_settings;

			if (!$sso_settings["sso_disqus"]["enabled"])  return false;

			if ($sso_settings["sso_disqus"]["client_id"] == "" || $sso_settings["sso_disqus"]["client_secret"] == "")  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_disqus"]["iprestrict"]) || SSO_IsSpammer($sso_settings["sso_disqus"]["iprestrict"]))  return false;

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings;

			$result = array();
			foreach (self::$fieldmap as $key => $info)
			{
				$key2 = $sso_settings["sso_disqus"]["map_" . $key];
				if ($key2 != "")  $result[$key2] = true;
			}

			return $result;
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_disqus" href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Log in with").' '.$this->DisplayName()); ?></a>
</div>
<?php
		}

		private function DisplayError($message)
		{
			global $sso_header, $sso_footer, $sso_target_url, $sso_providers, $sso_selectors_url;

			echo $sso_header;

			SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap">
<div class="sso_main_wrap_inner">
	<div class="sso_main_messages_wrap">
		<div class="sso_main_messages">
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($message); ?></div>
		</div>
	</div>

	<div class="sso_main_info"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Try again")); ?></a><?php if (count($sso_providers) > 1)  { ?> | <a href="<?php echo htmlspecialchars($sso_selectors_url); ?>"><?php echo htmlspecialchars(BB_Translate("Select another sign in method")); ?></a><?php } ?></div>
</div>
</div>
<?php
			echo $sso_footer;
		}

		public function ProcessFrontend()
		{
			global $sso_rng, $sso_provider, $sso_settings, $sso_session_info;

			$redirect_uri = BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_disqus_action=signin";

			if (isset($_REQUEST["sso_disqus_action"]) && $_REQUEST["sso_disqus_action"] == "signin")
			{
				// Recover the language settings.
				if (!isset($sso_session_info["sso_disqus_info"]))
				{
					$this->DisplayError(BB_Translate("Unable to authenticate the request."));

					return;
				}

				$url = BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_disqus_action=signin2";
				if (isset($_REQUEST["state"]))  $url .= "&state=" . urlencode($_REQUEST["state"]);
				if (isset($_REQUEST["code"]))  $url .= "&code=" . urlencode($_REQUEST["code"]);
				if (isset($_REQUEST["error"]))  $url .= "&error=" . urlencode($_REQUEST["error"]);
				$url .= "&lang=" . urlencode($sso_session_info["sso_disqus_info"]["lang"]);

				header("Location: " . $url);
			}
			else if (isset($_REQUEST["sso_disqus_action"]) && $_REQUEST["sso_disqus_action"] == "signin2")
			{
				// Validate the token.
				if (!isset($_REQUEST["state"]) || !isset($sso_session_info["sso_disqus_info"]) || $_REQUEST["state"] !== $sso_session_info["sso_disqus_info"]["token"])
				{
					$this->DisplayError(BB_Translate("Unable to authenticate the request."));

					return;
				}

				// Check for token expiration.
				if (CSDB::ConvertFromDBTime($sso_session_info["sso_disqus_info"]["expires"]) < time())
				{
					$this->DisplayError(BB_Translate("Verification token has expired."));

					return;
				}

				if (isset($_REQUEST["error"]))
				{
					if ($_REQUEST["error"] == "access_denied")  $message = BB_Translate("The request to sign in with Disqus was denied.");
					else  $message = BB_Translate("The error message returned was '%s'.", $_REQUEST["error"]);

					$this->DisplayError(BB_Translate("Log in failed.  %s", $message));

					return;
				}

				if (!isset($_REQUEST["code"]))
				{
					$this->DisplayError(BB_Translate("Log in failed.  Authorization code missing."));

					return;
				}

				// Get an access token from the authorization code.
				require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";
				require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/web_browser.php";

				$url = "https://disqus.com/api/oauth/2.0/access_token/";
				$options = array(
					"postvars" => array(
						"code" => $_REQUEST["code"],
						"client_id" => $sso_settings["sso_disqus"]["client_id"],
						"client_secret" => $sso_settings["sso_disqus"]["client_secret"],
						"redirect_uri" => $redirect_uri,
						"grant_type" => "authorization_code"
					)
				);
				$web = new WebBrowser();
				$result = $web->Process($url, "auto", $options);

				if (!$result["success"])  $this->DisplayError(BB_Translate("Log in failed.  Error retrieving URL for Disqus access token.  %s", $result["error"]));
				else if ($result["response"]["code"] != 200)  $this->DisplayError(BB_Translate("Log in failed.  The Disqus access token server returned:  %s", $result["response"]["code"] . " " . $result["response"]["meaning"]));
				else
				{
					// Get the access token.
					$data = @json_decode($result["body"], true);
					if ($data === false || !isset($data["access_token"]))  $this->DisplayError(BB_Translate("Log in failed.  Error retrieving access token from Disqus."));
					else
					{
						// Get the user's profile information.
						$url = "https://disqus.com/api/3.0/users/details.json?access_token=" . urlencode($data["access_token"])."&api_key=".$sso_settings["sso_disqus"]["client_id"]."&api_secret=".$sso_settings["sso_disqus"]["client_secret"];
						$result = $web->Process($url);

						if (!$result["success"])  $this->DisplayError(BB_Translate("Log in failed.  Error retrieving URL for Disqus profile information.  %s", $result["error"]));
						else if ($result["response"]["code"] != 200)  $this->DisplayError(BB_Translate("Log in failed.  The Disqus profile information server returned:  %s", $result["response"]["code"] . " " . $result["response"]["meaning"]));
						else
						{
							$profile = @json_decode($result["body"], true);
							if (! isset($profile['response']['id']))
							{
								$this->DisplayError(BB_Translate("Log in failed.  Error retrieving profile information from Disqus."));
							}
							else
							{
								$origprofile = $profile['response'];

								// Convert most profile fields into strings.
								foreach ($origprofile as $key => $val)
								{
									if (is_string($val))  continue;

									if (is_bool($val))  $val = (string)(int)$val;
									else if (is_numeric($val))  $val = (string)$val;
									else if (is_object($val) && isset($val->id) && isset($val->name))  $val = $val->name;
									else if (is_array($val) && isset($val['permalink']))  $val = $val['permalink'];

									$origprofile[$key] = $val;
								}

								$mapinfo = array();
								foreach (self::$fieldmap as $key => $info)
								{
									$key2 = $sso_settings["sso_disqus"]["map_" . $key];
									if ($key2 != "" && isset($origprofile[$key]))  $mapinfo[$key2] = $origprofile[$key];
								}

								SSO_ActivateUser($origprofile["id"], serialize($origprofile), $mapinfo);

								// Only falls through on account lockout or a fatal error.
								$this->DisplayError(BB_Translate("User activation failed."));
							}
						}
					}
				}
			}
			else
			{
				// Create internal data packet.
				$token = $sso_rng->GenerateString();
				$sso_session_info["sso_disqus_info"] = array(
					"lang" => (isset($_REQUEST["lang"]) ? $_REQUEST["lang"] : ""),
					"token" => $token,
					"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
				);
				if (!SSO_SaveSessionInfo())
				{
					$this->DisplayError(BB_Translate("Unable to save session information."));

					return;
				}

				// Calculate the required scope.
				$scope = "read,write";
				foreach (self::$fieldmap as $key => $info)
				{
					if ($info["extra"] != "" && $sso_settings["sso_disqus"]["map_" . $key] != "")  $scope[$info["extra"]] = true;
				}

				// Get the login redirection URL.
				$options = array(
					"response_type" => "code",
					"client_id" => $sso_settings["sso_disqus"]["client_id"],
					"redirect_uri" => $redirect_uri,
					"scope" => $scope,
					"state" => $token
				);
				$options2 = array();
				foreach ($options as $key => $val)  $options2[] = urlencode($key) . "=" . urlencode($val);
				$url = "https://disqus.com/api/oauth/2.0/authorize/?" . implode("&", $options2);

				SSO_ExternalRedirect($url);
			}
		}
	}