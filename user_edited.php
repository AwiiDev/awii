<?php
include "session.php"; include "functions.php";
if ((!$rPermissions["is_admin"]) OR ((!hasPermissions("adv", "add_user")) && (!hasPermissions("adv", "edit_user")))) { exit; }

if (isset($_POST["submit_user"])) {
    $_POST["mac_address_mag"] = strtoupper($_POST["mac_address_mag"]);
    $_POST["mac_address_e2"] = strtoupper($_POST["mac_address_e2"]);
    if (isset($_POST["edit"])) {
        if (!hasPermissions("adv", "edit_user")) { exit; }
        $rArray = getUser($_POST["edit"]);
        if (($rArray["is_mag"]) && (!hasPermissions("adv", "edit_mag"))) {
            exit;
        }
        if (($rArray["is_e2"]) && (!hasPermissions("adv", "edit_e2"))) {
            exit;
        }
        unset($rArray["id"]);
    } else {
        if (!hasPermissions("adv", "add_user")) { exit; }
        $rArray = Array("member_id" => 0, "username" => "", "password" => "", "exp_date" => null, "admin_enabled" => 1, "enabled" => 1, "admin_notes" => "", "reseller_notes" => "", "bouquet" => Array(), "max_connections" => 1, "is_restreamer" => 0, "allowed_ips" => Array(), "allowed_ua" => Array(), "created_at" => time(), "created_by" => -1, "is_mag" => 0, "is_e2" => 0, "force_server_id" => 0, "is_isplock" => 0, "isp_desc" => "", "forced_country" => "", "is_stalker" => 0, "bypass_ua" => 0, "play_token" => "");
    }

    if (strlen($_POST["username"]) == 0) {
        $_POST["username"] = rand(0000000000, 9999999999);;
    }
    if (strlen($_POST["password"]) == 0) {
        $_POST["password"] =  $_POST["username"] ;
    }
    if (!isset($_POST["edit"])) {
        $result = $db->query("SELECT `id` FROM `users` WHERE `username` = '".ESC($_POST["username"])."';");
        if (($result) && ($result->num_rows > 0)) {
            $_STATUS = 3; // Username in use.
        }
    }
    if ((($_POST["is_mag"]) && (!filter_var($_POST["mac_address_mag"], FILTER_VALIDATE_MAC))) OR ((strlen($_POST["mac_address_e2"]) > 0) && (!filter_var($_POST["mac_address_e2"], FILTER_VALIDATE_MAC)))) {
        $_STATUS = 4;
    } else if ($_POST["is_mag"]) {
        $result = $db->query("SELECT `user_id` FROM `mag_devices` WHERE mac = '".ESC(base64_encode($_POST["mac_address_mag"]))."' LIMIT 1;");
        if (($result) && ($result->num_rows > 0)) {
            if (isset($_POST["edit"])) {
                if (intval($result->fetch_assoc()["user_id"]) <> intval($_POST["edit"])) {
                    $_STATUS = 5; // MAC in use.
                }
            } else {
                $_STATUS = 5; // MAC in use.
            }
        }
    } else if ($_POST["is_e2"]) {
        $result = $db->query("SELECT `user_id` FROM `enigma2_devices` WHERE mac = '".ESC($_POST["mac_address_e2"])."' LIMIT 1;");
        if (($result) && ($result->num_rows > 0)) {
            if (isset($_POST["edit"])) {
                if (intval($result->fetch_assoc()["user_id"]) <> intval($_POST["edit"])) {
                    $_STATUS = 5; // MAC in use.
                }
            } else {
                $_STATUS = 5; // MAC in use.
            }
        }
    }
    foreach (Array("max_connections", "enabled", "admin_enabled") as $rSelection) {
        if (isset($_POST[$rSelection])) {
            $rArray[$rSelection] = intval($_POST[$rSelection]);
            unset($_POST[$rSelection]);
        } else {
            $rArray[$rSelection] = 1;
        }
    }
    foreach (Array("is_stalker", "is_e2", "is_mag", "is_restreamer", "is_trial") as $rSelection) {
        if (isset($_POST[$rSelection])) {
            $rArray[$rSelection] = 1;
            unset($_POST[$rSelection]);
        } else {
            $rArray[$rSelection] = 0;
        }
    }
    $rArray["bouquet"] = sortArrayByArray(array_values(json_decode($_POST["bouquets_selected"], True)), array_keys(getBouquetOrder()));
    $rArray["bouquet"] = "[".join(",", $rArray["bouquet"])."]";
    unset($_POST["bouquets_selected"]);
    if ((isset($_POST["exp_date"])) && (!isset($_POST["no_expire"]))) {
        if ((strlen($_POST["exp_date"]) > 0) AND ($_POST["exp_date"] <> "1970-01-01")) {
            try {
                $rDate = new DateTime($_POST["exp_date"]);
                $rArray["exp_date"] = $rDate->format("U");
            } catch (Exception $e) {
                echo "Incorrect date.";
                $_STATUS = 1;
            }
        }
        unset($_POST["exp_date"]);
    } else {
        $rArray["exp_date"] = null;
    }
    if (isset($_POST["allowed_ips"])) {
        if (!is_array($_POST["allowed_ips"])) {
            $_POST["allowed_ips"] = Array($_POST["allowed_ips"]);
        }
        $rArray["allowed_ips"] = json_encode($_POST["allowed_ips"]);
    } else {
        $rArray["allowed_ips"] = "[]";
    }
    if (isset($_POST["allowed_ua"])) {
        if (!is_array($_POST["allowed_ua"])) {
            $_POST["allowed_ua"] = Array($_POST["allowed_ua"]);
        }
        $rArray["allowed_ua"] = json_encode($_POST["allowed_ua"]);
    } else {
        $rArray["allowed_ua"] = "[]";
    }
    //isp lock_device
    if (isset($_POST["is_isplock"])) {
            $rArray["is_isplock"] = true;
        unset($_POST["is_isplock"]);
    } else {
        $rArray["is_isplock"] = false;
    }
    //isp lock_device
    if (!isset($_STATUS)) {

        if(@$_POST["quantity"] > 0){
            $quantity = $_POST["quantity"];
        }else{
            $quantity = 1;
        }
        for($loop=1;$loop<=$quantity;$loop++)
        {
            if($loop!=0){
                $random = rand(0000000000, 9999999999);
                $_POST["username"] = $random;
                $_POST["password"] =  $random;
            }
            foreach($_POST as $rKey => $rValue) {
                if (isset($rArray[$rKey])) {
                    $rArray[$rKey] = $rValue;
                }
            }
            
            if (!$rArray["member_id"]) {
                $rArray["member_id"] = -1;
            }
            $rArray["created_by"] = $rArray["member_id"];
            $rCols = "`".ESC(implode('`,`', array_keys($rArray)))."`";
            $rValues = '';
            foreach (array_values($rArray) as $rValue) {
                isset($rValues) ? $rValues .= ',' : $rValues = '';
                if (is_array($rValue)) {
                    $rValue = json_encode($rValue);
                }
                if (is_null($rValue)) {
                    $rValues .= 'NULL';
                } else {
                    $rValues .= '\''.ESC($rValue).'\'';
                }
            }
            $rValues = trim($rValues,",");
            if (isset($_POST["edit"])) {
                $rCols = "`id`,".$rCols;
                $rValues = ESC($_POST["edit"]).",".$rValues;
            }
            $rQuery = "REPLACE INTO `users`(".$rCols.") VALUES(".$rValues.");";
           
           /* $db->query($rQuery);
            echo "id-".$db->insert_id;
            echo "error-".$db->error;
            exit;*/
            if ($db->query($rQuery)) {
                if (isset($_POST["edit"])) {
                    $rInsertID = intval($_POST["edit"]);
                } else {
                    $rInsertID = $db->insert_id;
                }
                if ((isset($rInsertID)) && (isset($_POST["access_output"]))) {
                    $db->query("DELETE FROM `user_output` WHERE `user_id` = ".intval($rInsertID).";");
                    foreach ($_POST["access_output"] as $rOutputID) {
                        $db->query("INSERT INTO `user_output`(`user_id`, `access_output_id`) VALUES(".intval($rInsertID).", ".intval($rOutputID).");");
                    }
                    if ($rArray["is_mag"] == 1) {
                        if (hasPermissions("adv", "add_mag")) {
                            if (isset($_POST["lock_device"])) {
                                $rSTBLock = 1;
                            } else {
                                $rSTBLock = 0;
                            }
                            $result = $db->query("SELECT `mag_id` FROM `mag_devices` WHERE `user_id` = ".intval($rInsertID)." LIMIT 1;");
                            if ((isset($result)) && ($result->num_rows == 1)) {
                                $db->query("UPDATE `mag_devices` SET `mac` = '".base64_encode(ESC($_POST["mac_address_mag"]))."', `lock_device` = ".intval($rSTBLock)." WHERE `user_id` = ".intval($rInsertID).";");
                            } else {
                                $db->query("INSERT INTO `mag_devices`(`user_id`, `mac`, `lock_device`) VALUES(".intval($rInsertID).", '".ESC(base64_encode($_POST["mac_address_mag"]))."', ".intval($rSTBLock).");");
                            }
                            if (isset($_POST["edit"])) {
                                $db->query("DELETE FROM `enigma2_devices` WHERE `user_id` = ".intval($rInsertID).";");
                            }
                        }
                    } else if ($rArray["is_e2"] == 1) {
                        if (hasPermissions("adv", "add_e2")) {
                            $result = $db->query("SELECT `device_id` FROM `enigma2_devices` WHERE `user_id` = ".intval($rInsertID)." LIMIT 1;");
                            if ((isset($result)) && ($result->num_rows == 1)) {
                                $db->query("UPDATE `enigma2_devices` SET `mac` = '".ESC($_POST["mac_address_e2"])."' WHERE `user_id` = ".intval($rInsertID).";");
                            } else {
                                $db->query("INSERT INTO `enigma2_devices`(`user_id`, `mac`) VALUES(".intval($rInsertID).", '".ESC($_POST["mac_address_e2"])."');");
                            }
                            if (isset($_POST["edit"])) {
                                $db->query("DELETE FROM `mag_devices` WHERE `user_id` = ".intval($rInsertID).";");
                            }
                        }
                    } else if (isset($_POST["edit"])) {
                        $db->query("DELETE FROM `mag_devices` WHERE `user_id` = ".intval($rInsertID).";");
                        $db->query("DELETE FROM `enigma2_devices` WHERE `user_id` = ".intval($rInsertID).";");
                    }
                }
                if($loop == $quantity){
                    header("Location: ./user.php?id=".$rInsertID); exit;
                }
            } else {
                $_STATUS = 2;
            }
        }//End loop
    }
}

if (isset($_GET["id"])) {
    $rUser = getUser($_GET["id"]);
    if ((!$rUser) OR (!hasPermissions("adv", "edit_user"))) {
        exit;
    }
    if (($rUser["is_mag"]) && (!hasPermissions("adv", "edit_mag"))) {
        exit;
    }
    if (($rUser["is_e2"]) && (!hasPermissions("adv", "edit_e2"))) {
        exit;
    }
    $rMAGUser = getMAGUser($_GET["id"]);
    if (($rUser["is_mag"])) {
        $rUser["lock_device"] = $rMAGUser["lock_device"];
        $rUser["mac_address_mag"] = base64_decode($rMAGUser["mac"]);
    }
    if (($rUser["is_e2"])) {
        $rUser["mac_address_e2"] = getE2User($_GET["id"])["mac"];
    }
    $rUser["outputs"] = getOutputs($rUser["id"]);
} else if (!hasPermissions("adv", "add_user")) { exit; }

$rRegisteredUsers = getRegisteredUsers();
$rCountries = Array(Array("id" => "", "name" => "Off"), Array("id" => "A1", "name" => "Anonymous Proxy"), Array("id" => "A2", "name" => "Satellite Provider"), Array("id" => "O1", "name" => "Other Country"), Array("id" => "AF", "name" => "Afghanistan"), Array("id" => "AX", "name" => "Aland Islands"), Array("id" => "AL", "name" => "Albania"), Array("id" => "DZ", "name" => "Algeria"), Array("id" => "AS", "name" => "American Samoa"), Array("id" => "AD", "name" => "Andorra"), Array("id" => "AO", "name" => "Angola"), Array("id" => "AI", "name" => "Anguilla"), Array("id" => "AQ", "name" => "Antarctica"), Array("id" => "AG", "name" => "Antigua And Barbuda"), Array("id" => "AR", "name" => "Argentina"), Array("id" => "AM", "name" => "Armenia"), Array("id" => "AW", "name" => "Aruba"), Array("id" => "AU", "name" => "Australia"), Array("id" => "AT", "name" => "Austria"), Array("id" => "AZ", "name" => "Azerbaijan"), Array("id" => "BS", "name" => "Bahamas"), Array("id" => "BH", "name" => "Bahrain"), Array("id" => "BD", "name" => "Bangladesh"), Array("id" => "BB", "name" => "Barbados"), Array("id" => "BY", "name" => "Belarus"), Array("id" => "BE", "name" => "Belgium"), Array("id" => "BZ", "name" => "Belize"), Array("id" => "BJ", "name" => "Benin"), Array("id" => "BM", "name" => "Bermuda"), Array("id" => "BT", "name" => "Bhutan"), Array("id" => "BO", "name" => "Bolivia"), Array("id" => "BA", "name" => "Bosnia And Herzegovina"), Array("id" => "BW", "name" => "Botswana"), Array("id" => "BV", "name" => "Bouvet Island"), Array("id" => "BR", "name" => "Brazil"), Array("id" => "IO", "name" => "British Indian Ocean Territory"), Array("id" => "BN", "name" => "Brunei Darussalam"), Array("id" => "BG", "name" => "Bulgaria"), Array("id" => "BF", "name" => "Burkina Faso"), Array("id" => "BI", "name" => "Burundi"), Array("id" => "KH", "name" => "Cambodia"), Array("id" => "CM", "name" => "Cameroon"), Array("id" => "CA", "name" => "Canada"), Array("id" => "CV", "name" => "Cape Verde"), Array("id" => "KY", "name" => "Cayman Islands"), Array("id" => "CF", "name" => "Central African Republic"), Array("id" => "TD", "name" => "Chad"), Array("id" => "CL", "name" => "Chile"), Array("id" => "CN", "name" => "China"), Array("id" => "CX", "name" => "Christmas Island"), Array("id" => "CC", "name" => "Cocos (Keeling) Islands"), Array("id" => "CO", "name" => "Colombia"), Array("id" => "KM", "name" => "Comoros"), Array("id" => "CG", "name" => "Congo"), Array("id" => "CD", "name" => "Congo, Democratic Republic"), Array("id" => "CK", "name" => "Cook Islands"), Array("id" => "CR", "name" => "Costa Rica"), Array("id" => "CI", "name" => "Cote D'Ivoire"), Array("id" => "HR", "name" => "Croatia"), Array("id" => "CU", "name" => "Cuba"), Array("id" => "CY", "name" => "Cyprus"), Array("id" => "CZ", "name" => "Czech Republic"), Array("id" => "DK", "name" => "Denmark"), Array("id" => "DJ", "name" => "Djibouti"), Array("id" => "DM", "name" => "Dominica"), Array("id" => "DO", "name" => "Dominican Republic"), Array("id" => "EC", "name" => "Ecuador"), Array("id" => "EG", "name" => "Egypt"), Array("id" => "SV", "name" => "El Salvador"), Array("id" => "GQ", "name" => "Equatorial Guinea"), Array("id" => "ER", "name" => "Eritrea"), Array("id" => "EE", "name" => "Estonia"), Array("id" => "ET", "name" => "Ethiopia"), Array("id" => "FK", "name" => "Falkland Islands (Malvinas)"), Array("id" => "FO", "name" => "Faroe Islands"), Array("id" => "FJ", "name" => "Fiji"), Array("id" => "FI", "name" => "Finland"), Array("id" => "FR", "name" => "France"), Array("id" => "GF", "name" => "French Guiana"), Array("id" => "PF", "name" => "French Polynesia"), Array("id" => "TF", "name" => "French Southern Territories"), Array("id" => "MK", "name" => "Fyrom"), Array("id" => "GA", "name" => "Gabon"), Array("id" => "GM", "name" => "Gambia"), Array("id" => "GE", "name" => "Georgia"), Array("id" => "DE", "name" => "Germany"), Array("id" => "GH", "name" => "Ghana"), Array("id" => "GI", "name" => "Gibraltar"), Array("id" => "GR", "name" => "Greece"), Array("id" => "GL", "name" => "Greenland"), Array("id" => "GD", "name" => "Grenada"), Array("id" => "GP", "name" => "Guadeloupe"), Array("id" => "GU", "name" => "Guam"), Array("id" => "GT", "name" => "Guatemala"), Array("id" => "GG", "name" => "Guernsey"), Array("id" => "GN", "name" => "Guinea"), Array("id" => "GW", "name" => "Guinea-Bissau"), Array("id" => "GY", "name" => "Guyana"), Array("id" => "HT", "name" => "Haiti"), Array("id" => "HM", "name" => "Heard Island & Mcdonald Islands"), Array("id" => "VA", "name" => "Holy See (Vatican City State)"), Array("id" => "HN", "name" => "Honduras"), Array("id" => "HK", "name" => "Hong Kong"), Array("id" => "HU", "name" => "Hungary"), Array("id" => "IS", "name" => "Iceland"), Array("id" => "IN", "name" => "India"), Array("id" => "ID", "name" => "Indonesia"), Array("id" => "IR", "name" => "Iran, Islamic Republic Of"), Array("id" => "IQ", "name" => "Iraq"), Array("id" => "IE", "name" => "Ireland"), Array("id" => "IM", "name" => "Isle Of Man"), Array("id" => "IL", "name" => "Israel"), Array("id" => "IT", "name" => "Italy"), Array("id" => "JM", "name" => "Jamaica"), Array("id" => "JP", "name" => "Japan"), Array("id" => "JE", "name" => "Jersey"), Array("id" => "JO", "name" => "Jordan"), Array("id" => "KZ", "name" => "Kazakhstan"), Array("id" => "KE", "name" => "Kenya"), Array("id" => "KI", "name" => "Kiribati"), Array("id" => "KR", "name" => "Korea"), Array("id" => "KW", "name" => "Kuwait"), Array("id" => "KG", "name" => "Kyrgyzstan"), Array("id" => "LA", "name" => "Lao People's Democratic Republic"), Array("id" => "LV", "name" => "Latvia"), Array("id" => "LB", "name" => "Lebanon"), Array("id" => "LS", "name" => "Lesotho"), Array("id" => "LR", "name" => "Liberia"), Array("id" => "LY", "name" => "Libyan Arab Jamahiriya"), Array("id" => "LI", "name" => "Liechtenstein"), Array("id" => "LT", "name" => "Lithuania"), Array("id" => "LU", "name" => "Luxembourg"), Array("id" => "MO", "name" => "Macao"), Array("id" => "MG", "name" => "Madagascar"), Array("id" => "MW", "name" => "Malawi"), Array("id" => "MY", "name" => "Malaysia"), Array("id" => "MV", "name" => "Maldives"), Array("id" => "ML", "name" => "Mali"), Array("id" => "MT", "name" => "Malta"), Array("id" => "MH", "name" => "Marshall Islands"), Array("id" => "MQ", "name" => "Martinique"), Array("id" => "MR", "name" => "Mauritania"), Array("id" => "MU", "name" => "Mauritius"), Array("id" => "YT", "name" => "Mayotte"), Array("id" => "MX", "name" => "Mexico"), Array("id" => "FM", "name" => "Micronesia, Federated States Of"), Array("id" => "MD", "name" => "Moldova"), Array("id" => "MC", "name" => "Monaco"), Array("id" => "MN", "name" => "Mongolia"), Array("id" => "ME", "name" => "Montenegro"), Array("id" => "MS", "name" => "Montserrat"), Array("id" => "MA", "name" => "Morocco"), Array("id" => "MZ", "name" => "Mozambique"), Array("id" => "MM", "name" => "Myanmar"), Array("id" => "NA", "name" => "Namibia"), Array("id" => "NR", "name" => "Nauru"), Array("id" => "NP", "name" => "Nepal"), Array("id" => "NL", "name" => "Netherlands"), Array("id" => "AN", "name" => "Netherlands Antilles"), Array("id" => "NC", "name" => "New Caledonia"), Array("id" => "NZ", "name" => "New Zealand"), Array("id" => "NI", "name" => "Nicaragua"), Array("id" => "NE", "name" => "Niger"), Array("id" => "NG", "name" => "Nigeria"), Array("id" => "NU", "name" => "Niue"), Array("id" => "NF", "name" => "Norfolk Island"), Array("id" => "MP", "name" => "Northern Mariana Islands"), Array("id" => "NO", "name" => "Norway"), Array("id" => "OM", "name" => "Oman"), Array("id" => "PK", "name" => "Pakistan"), Array("id" => "PW", "name" => "Palau"), Array("id" => "PS", "name" => "Palestinian Territory, Occupied"), Array("id" => "PA", "name" => "Panama"), Array("id" => "PG", "name" => "Papua New Guinea"), Array("id" => "PY", "name" => "Paraguay"), Array("id" => "PE", "name" => "Peru"), Array("id" => "PH", "name" => "Philippines"), Array("id" => "PN", "name" => "Pitcairn"), Array("id" => "PL", "name" => "Poland"), Array("id" => "PT", "name" => "Portugal"), Array("id" => "PR", "name" => "Puerto Rico"), Array("id" => "QA", "name" => "Qatar"), Array("id" => "RE", "name" => "Reunion"), Array("id" => "RO", "name" => "Romania"), Array("id" => "RU", "name" => "Russian Federation"), Array("id" => "RW", "name" => "Rwanda"), Array("id" => "BL", "name" => "Saint Barthelemy"), Array("id" => "SH", "name" => "Saint Helena"), Array("id" => "KN", "name" => "Saint Kitts And Nevis"), Array("id" => "LC", "name" => "Saint Lucia"), Array("id" => "MF", "name" => "Saint Martin"), Array("id" => "PM", "name" => "Saint Pierre And Miquelon"), Array("id" => "VC", "name" => "Saint Vincent And Grenadines"), Array("id" => "WS", "name" => "Samoa"), Array("id" => "SM", "name" => "San Marino"), Array("id" => "ST", "name" => "Sao Tome And Principe"), Array("id" => "SA", "name" => "Saudi Arabia"), Array("id" => "SN", "name" => "Senegal"), Array("id" => "RS", "name" => "Serbia"), Array("id" => "SC", "name" => "Seychelles"), Array("id" => "SL", "name" => "Sierra Leone"), Array("id" => "SG", "name" => "Singapore"), Array("id" => "SK", "name" => "Slovakia"), Array("id" => "SI", "name" => "Slovenia"), Array("id" => "SB", "name" => "Solomon Islands"), Array("id" => "SO", "name" => "Somalia"), Array("id" => "ZA", "name" => "South Africa"), Array("id" => "GS", "name" => "South Georgia And Sandwich Isl."), Array("id" => "ES", "name" => "Spain"), Array("id" => "LK", "name" => "Sri Lanka"), Array("id" => "SD", "name" => "Sudan"), Array("id" => "SR", "name" => "Suriname"), Array("id" => "SJ", "name" => "Svalbard And Jan Mayen"), Array("id" => "SZ", "name" => "Swaziland"), Array("id" => "SE", "name" => "Sweden"), Array("id" => "CH", "name" => "Switzerland"), Array("id" => "SY", "name" => "Syrian Arab Republic"), Array("id" => "TW", "name" => "Taiwan"), Array("id" => "TJ", "name" => "Tajikistan"), Array("id" => "TZ", "name" => "Tanzania"), Array("id" => "TH", "name" => "Thailand"), Array("id" => "TL", "name" => "Timor-Leste"), Array("id" => "TG", "name" => "Togo"), Array("id" => "TK", "name" => "Tokelau"), Array("id" => "TO", "name" => "Tonga"), Array("id" => "TT", "name" => "Trinidad And Tobago"), Array("id" => "TN", "name" => "Tunisia"), Array("id" => "TR", "name" => "Turkey"), Array("id" => "TM", "name" => "Turkmenistan"), Array("id" => "TC", "name" => "Turks And Caicos Islands"), Array("id" => "TV", "name" => "Tuvalu"), Array("id" => "UG", "name" => "Uganda"), Array("id" => "UA", "name" => "Ukraine"), Array("id" => "AE", "name" => "United Arab Emirates"), Array("id" => "GB", "name" => "United Kingdom"), Array("id" => "US", "name" => "United States"), Array("id" => "UM", "name" => "United States Outlying Islands"), Array("id" => "UY", "name" => "Uruguay"), Array("id" => "UZ", "name" => "Uzbekistan"), Array("id" => "VU", "name" => "Vanuatu"), Array("id" => "VE", "name" => "Venezuela"), Array("id" => "VN", "name" => "Viet Nam"), Array("id" => "VG", "name" => "Virgin Islands, British"), Array("id" => "VI", "name" => "Virgin Islands, U.S."), Array("id" => "WF", "name" => "Wallis And Futuna"), Array("id" => "EH", "name" => "Western Sahara"), Array("id" => "YE", "name" => "Yemen"), Array("id" => "ZM", "name" => "Zambia"), Array("id" => "ZW", "name" => "Zimbabwe"));

if ($rSettings["sidebar"]) {
    include "header_sidebar.php";
} else {
    include "header.php";
}
        if ($rSettings["sidebar"]) { ?>
        <div class="content-page"><div class="content boxed-layout"><div class="container-fluid">
        <?php } else { ?>
        <div class="wrapper boxed-layout"><div class="container-fluid">
        <?php } ?>
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <a href="./users.php<?php if (isset($_GET["mag"])) { echo "?mag"; } else if (isset($_GET["e2"])) { echo "?e2"; } ?>"><li class="breadcrumb-item"><i class="mdi mdi-backspace"></i> <?=$_["back_to_users"]?></li></a>
                                </ol>
                            </div>
                            <h4 class="page-title"><?php if (isset($rUser)) { echo $_["edit"]; } else { echo $_["add"]; } ?> <?=$_["user"]?></h4>
                        </div>
                    </div>
                </div>     
                <!-- end page title --> 
                <div class="row">
                    <div class="col-xl-12">
                        <?php if (isset($_STATUS)) {
                        if ($_STATUS == 0) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["user_operation_was_completed_successfully"]?>
                        </div>
                        <?php } else if ($_STATUS == 1) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["an_incorrect_expiration_date_was_entered"]?>
                        </div>
                        <?php } else if ($_STATUS == 2) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["generic_fail"]?>
                        </div>
                        <?php } else if ($_STATUS == 3) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["this_username_already_exists"]?>
                        </div>
                        <?php } else if ($_STATUS == 4) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["an_invalid_mac_address_was_entered"]?>
                        </div>
                        <?php } else if ($_STATUS == 5) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?=$_["this_mac_address_is_already_in_use"]?>
                        </div>
                        <?php } 
                        } ?>
                        <div class="card">
                            <div class="card-body">
                                <form action="./user.php<?php if (isset($_GET["id"])) { echo "?id=".$_GET["id"]; } ?>" method="POST" id="user_form" data-parsley-validate="">
                                    <?php if (isset($rUser)) { ?>
                                    <input type="hidden" name="edit" value="<?=$rUser["id"]?>" />
                                    <input type="hidden" name="admin_enabled" value="<?=$rUser["admin_enabled"]?>" />
                                    <input type="hidden" name="enabled" value="<?=$rUser["enabled"]?>" />
                                    <?php } ?>
                                    <input type="hidden" name="bouquets_selected" id="bouquets_selected" value="" />
                                    <div id="basicwizard">
                                        <ul class="nav nav-pills bg-light nav-justified form-wizard-header mb-4">
                                            <li class="nav-item">
                                                <a href="#user-details" data-toggle="tab" class="nav-link rounded-0 pt-2 pb-2"> 
                                                    <i class="mdi mdi-account-card-details-outline mr-1"></i>
                                                    <span class="d-none d-sm-inline"><?=$_["details"]?></span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#advanced-options" data-toggle="tab" class="nav-link rounded-0 pt-2 pb-2">
                                                    <i class="mdi mdi-folder-alert-outline mr-1"></i>
                                                    <span class="d-none d-sm-inline"><?=$_["advanced"]?></span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#restrictions" data-toggle="tab" class="nav-link rounded-0 pt-2 pb-2">
                                                    <i class="mdi mdi-hazard-lights mr-1"></i>
                                                    <span class="d-none d-sm-inline"><?=$_["restrictions"]?></span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#bouquets" data-toggle="tab" class="nav-link rounded-0 pt-2 pb-2">
                                                    <i class="mdi mdi-flower-tulip mr-1"></i>
                                                    <span class="d-none d-sm-inline"><?=$_["bouquets"]?></span>
                                                </a>
                                            </li>
                                        </ul>
                                        <div class="tab-content b-0 mb-0 pt-0">
                                            <div class="tab-pane" id="user-details">
                                                <div class="row">
                                                    <div class="col-12">



                                                        <div class="form-group row mb-4">
                                                                <label class="col-md-4 col-form-label" for="quantity">Quantity</label>
                                                                <div class="col-md-8">
                                                                    <input type="number" class="form-control"
                                                                           id="quantity" name="quantity"
                                                                           placeholder="Enter The Quantity" required  min="1">
                                                                </div>
                                                            </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="username"><?=$_["username"]?></label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="username" name="username" placeholder="<?=$_["auto_generate_if_blank"]?>" value="<?php if (isset($rUser)) { echo htmlspecialchars($rUser["username"]); } ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="password"><?=$_["password"]?></label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="password" name="password" placeholder="<?=$_["auto_generate_if_blank"]?>" value="<?php if (isset($rUser)) { echo htmlspecialchars($rUser["password"]); } ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="member_id"><?=$_["owner"]?></label>
                                                            <div class="col-md-8">
                                                                <select name="member_id" id="member_id" class="form-control select2" data-toggle="select2">
                                                                    <option value="-1"><?=$_["no_owner"]?></option>
                                                                    <?php foreach ($rRegisteredUsers as $rRegisteredUser) { ?>
                                                                    <option <?php if (isset($rUser)) { if (intval($rUser["member_id"]) == intval($rRegisteredUser["id"])) { echo "selected "; } } else { if (intval($rUserInfo["id"]) == intval($rRegisteredUser["id"])) { echo "selected "; } } ?>value="<?=$rRegisteredUser["id"]?>"><?=$rRegisteredUser["username"]?></option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="max_connections"><?=$_["max_connections"]?></label>
                                                            <div class="col-md-2">
                                                                <input type="text" class="form-control" id="max_connections" name="max_connections" value="<?php if (isset($rUser)) { echo htmlspecialchars($rUser["max_connections"]); } else { echo "1"; } ?>" required data-parsley-trigger="<?=$_["change"]?>">
                                                            </div>
                                                            <label class="col-md-2 col-form-label" for="exp_date"><?=$_["expiry"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["leave_blank_for_unlimited"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-2" style="padding-right: 0px; padding-left: 0px;">
                                                                <input type="text" style="padding-right: 1px; padding-left: 1px;" class="form-control text-center datetime" id="exp_date" name="exp_date" value="<?php if (isset($rUser)) { if (!is_null($rUser["exp_date"])) { echo date("Y-m-d HH:mm", $rUser["exp_date"]); } else { echo "\" disabled=\"disabled"; } } ?>" data-toggle="date-picker" data-single-date-picker="true">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="custom-control custom-checkbox mt-1">
                                                                    <input type="checkbox" class="custom-control-input" id="no_expire" name="no_expire"<?php if(isset($rUser)) { if (is_null($rUser["exp_date"])) { echo " checked"; } } ?>>
                                                                    <label class="custom-control-label" for="no_expire"><?=$_["never"]?></label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="admin_notes"><?=$_["admin_notes"]?></label>
                                                            <div class="col-md-8">
                                                                <textarea id="admin_notes" name="admin_notes" class="form-control" rows="3" placeholder=""><?php if (isset($rUser)) { echo htmlspecialchars($rUser["admin_notes"]); } ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="reseller_notes"><?=$_["reseller_notes"]?></label>
                                                            <div class="col-md-8">
                                                                <textarea id="reseller_notes" name="reseller_notes" class="form-control" rows="3" placeholder=""><?php if (isset($rUser)) { echo htmlspecialchars($rUser["reseller_notes"]); } ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end col -->
                                                </div> <!-- end row -->
                                                <ul class="list-inline wizard mb-0">
                                                    <li class="next list-inline-item float-right">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["next"]?></a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="tab-pane" id="advanced-options">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="force_server_id"><?=$_["forced_connection"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["force_this_user_to_connect_to"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-8">
                                                                <select name="force_server_id" id="force_server_id" class="form-control select2" data-toggle="select2">
                                                                    <option <?php if (isset($rUser)) { if (intval($rUser["force_server_id"]) == 0) { echo "selected "; } } ?>value="0"><?=$_["disabled"]?></option>
                                                                    <?php foreach ($rServers as $rServer) { ?>
                                                                    <option <?php if (isset($rUser)) { if (intval($rUser["force_server_id"]) == intval($rServer["id"])) { echo "selected "; } } ?>value="<?=$rServer["id"]?>"><?=htmlspecialchars($rServer["server_name"])?></option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="is_stalker"><?=$_["ministra_portal"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["select_this_option"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-2">
                                                                <input name="is_stalker" id="is_stalker" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_stalker"] == 1) { echo "checked "; } } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                            <label class="col-md-4 col-form-label" for="is_restreamer"><?=$_["restreamer"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["if_selected_this_user"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-2">
                                                                <input name="is_restreamer" id="is_restreamer" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_restreamer"] == 1) { echo "checked "; } } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="is_e2"><?=$_["enigma_device"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["this_option_will_be_selected_enigma"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-2">
                                                                <input <?php if (!hasPermissions("adv", "add_e2")) { echo "disabled "; } ?>name="is_e2" id="is_e2" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_e2"] == 1) { echo "checked "; } } else if ((isset($_GET["e2"])) && (hasPermissions("adv", "add_e2"))) { echo "checked "; } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                            <label class="col-md-4 col-form-label" for="is_mag"><?=$_["mag_device"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["this_option_will_be_selected_mag"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-2">
                                                                <input <?php if (!hasPermissions("adv", "add_mag")) { echo "disabled "; } ?>name="is_mag" id="is_mag" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_mag"] == 1) { echo "checked "; } } else if ((isset($_GET["mag"])) && (hasPermissions("adv", "add_mag"))) { echo "checked "; } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="is_trial"><?=$_["trial_account"]?></label>
                                                            <div class="col-md-2">
                                                                <input name="is_trial" id="is_trial" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_trial"] == 1) { echo "checked "; } } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                            <label class="col-md-4 col-form-label" for="lock_device"><?=$_["mag_stb_lock"]?></label>
                                                            <div class="col-md-2">
                                                                <input name="lock_device" id="lock_device" type="checkbox" <?php if (isset($rUser)) { if ($rUser["lock_device"] == 1) { echo "checked "; } } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="is_isplock">ISP LOCK</label>
                                                            <div class="col-md-2">
                                                                <input name="is_isplock" id="is_isplock" type="checkbox" <?php if (isset($rUser)) { if ($rUser["is_isplock"] == 1) { echo "checked "; } } ?>data-plugin="switchery" class="js-switch" data-color="#039cfd"/>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4" style="display:none" id="mac_entry_mag">
                                                            <label class="col-md-4 col-form-label" for="mac_address_mag"><?=$_["mac_address"]?></label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="mac_address_mag" name="mac_address_mag" value="<?php if (isset($rUser)) { echo htmlspecialchars($rUser["mac_address_mag"]); } ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4" style="display:none" id="mac_entry_e2">
                                                            <label class="col-md-4 col-form-label" for="mac_address_e2"><?=$_["mac_address"]?></label>
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" id="mac_address_e2" name="mac_address_e2" value="<?php if (isset($rUser)) { echo htmlspecialchars($rUser["mac_address_e2"]); } ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="forced_country"><?=$_["forced_country"]?> <i data-toggle="tooltip" data-placement="top" title="" data-original-title="<?=$_["force_user_to_connect"]?>" class="mdi mdi-information"></i></label>
                                                            <div class="col-md-8">
                                                                <select name="forced_country" id="forced_country" class="form-control select2" data-toggle="select2">
                                                                    <?php foreach ($rCountries as $rCountry) { ?>
                                                                    <option <?php if (isset($rUser)) { if ($rUser["forced_country"] == $rCountry["id"]) { echo "selected "; } } ?>value="<?=$rCountry["id"]?>"><?=$rCountry["name"]?></option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="access_output"><?=$_["access_output"]?></label>
                                                            <div class="col-md-8">
                                                                <?php foreach (getOutputs() as $rOutput) { ?>
                                                                <div class="checkbox form-check-inline">
                                                                    <input data-size="large" type="checkbox" id="access_output_<?=$rOutput["access_output_id"]?>" name="access_output[]" value="<?=$rOutput["access_output_id"]?>"<?php if (isset($rUser)) { if (in_array($rOutput["access_output_id"], $rUser["outputs"])) { echo " checked"; } } else { echo " checked"; } ?>>
                                                                    <label for="access_output_<?=$rOutput["access_output_id"]?>"> <?=$rOutput["output_name"]?> </label>
                                                                </div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end col -->
                                                </div> <!-- end row -->
                                                <ul class="list-inline wizard mb-0">
                                                    <li class="previous list-inline-item">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["prev"]?></a>
                                                    </li>
                                                    <li class="next list-inline-item float-right">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["next"]?></a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="tab-pane" id="restrictions">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="ip_field"><?=$_["allowed_ip_addresses"]?></label>
                                                            <div class="col-md-8 input-group">
                                                                <input type="text" id="ip_field" class="form-control" value="">
                                                                <div class="input-group-append">
                                                                    <a href="javascript:void(0)" id="add_ip" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-plus"></i></a>
                                                                    <a href="javascript:void(0)" id="remove_ip" class="btn btn-danger waves-effect waves-light"><i class="mdi mdi-close"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="allowed_ips">&nbsp;</label>
                                                            <div class="col-md-8">
                                                                <select id="allowed_ips" name="allowed_ips[]" size=6 class="form-control" multiple="multiple">
                                                                <?php if (isset($rUser)) { foreach(json_decode($rUser["allowed_ips"], True) as $rIP) { ?>
                                                                <option value="<?=$rIP?>"><?=$rIP?></option>
                                                                <?php } } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="ua_field"><?=$_["allowed_user-agents"]?></label>
                                                            <div class="col-md-8 input-group">
                                                                <input type="text" id="ua_field" class="form-control" value="">
                                                                <div class="input-group-append">
                                                                    <a href="javascript:void(0)" id="add_ua" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-plus"></i></a>
                                                                    <a href="javascript:void(0)" id="remove_ua" class="btn btn-danger waves-effect waves-light"><i class="mdi mdi-close"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group row mb-4">
                                                            <label class="col-md-4 col-form-label" for="allowed_ua">&nbsp;</label>
                                                            <div class="col-md-8">
                                                                <select id="allowed_ua" name="allowed_ua[]" size=6 class="form-control" multiple="multiple">
                                                                <?php if (isset($rUser)) { foreach(json_decode($rUser["allowed_ua"], True) as $rUA) { ?>
                                                                <option value="<?=$rUA?>"><?=$rUA?></option>
                                                                <?php } } ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end col -->
                                                </div> <!-- end row -->
                                                <ul class="list-inline wizard mb-0">
                                                    <li class="previous list-inline-item">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["prev"]?></a>
                                                    </li>
                                                    <li class="next list-inline-item float-right">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["next"]?></a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="tab-pane" id="bouquets">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="form-group row mb-4">
                                                            <table id="datatable-bouquets" class="table table-borderless mb-0">
                                                                <thead class="bg-light">
                                                                    <tr>
                                                                        <th class="text-center"><?=$_["id"]?></th>
                                                                        <th><?=$_["bouquet_name"]?></th>
                                                                        <th class="text-center"><?=$_["streams"]?></th>
                                                                        <th class="text-center"><?=$_["series"]?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach (getBouquets() as $rBouquet) { ?>
                                                                    <tr<?php if (isset($rUser)) { if(in_array($rBouquet["id"], json_decode($rUser["bouquet"], True))) { echo " class='selected selectedfilter ui-selected'"; } } ?>>
                                                                        <td class="text-center"><?=$rBouquet["id"]?></td>
                                                                        <td><?=$rBouquet["bouquet_name"]?></td>
                                                                        <td class="text-center"><?=count(json_decode($rBouquet["bouquet_channels"], True))?></td>
                                                                        <td class="text-center"><?=count(json_decode($rBouquet["bouquet_series"], True))?></td>
                                                                    </tr>
                                                                    <?php } ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div> <!-- end col -->
                                                </div> <!-- end row -->
                                                <ul class="list-inline wizard mb-0">
                                                    <li class="previous list-inline-item">
                                                        <a href="javascript: void(0);" class="btn btn-secondary"><?=$_["prev"]?></a>
                                                    </li>
                                                    <li class="list-inline-item float-right">
                                                        <a href="javascript: void(0);" onClick="toggleBouquets()" class="btn btn-info"><?=$_["toggle_bouquets"]?></a>
                                                        <input name="submit_user" type="submit" class="btn btn-primary" value="<?php if (isset($rUser)) { echo $_["edit"]; } else { echo $_["add"]; } ?>" />
                                                    </li>
                                                </ul>
                                            </div>
                                        </div> <!-- tab-content -->
                                    </div> <!-- end #basicwizard-->
                                </form>
                            </div> <!-- end card-body -->
                        </div> <!-- end card-->
                    </div> <!-- end col -->
                </div>
            </div> <!-- end container -->
        </div>
        <!-- end wrapper -->
        <?php if ($rSettings["sidebar"]) { echo "</div>"; } ?>
        <!-- Footer Start -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 copyright text-center">Copyright © 2020 <?=htmlspecialchars($rSettings["server_name"])?></div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->
        <script src="assets/js/vendor.min.js"></script>
        <script src="assets/libs/jquery-toast/jquery.toast.min.js"></script>
        <script src="assets/libs/jquery-ui/jquery-ui.min.js"></script>
        <script src="assets/libs/jquery-nice-select/jquery.nice-select.min.js"></script>
        <script src="assets/libs/switchery/switchery.min.js"></script>
        <script src="assets/libs/select2/select2.min.js"></script>
        <script src="assets/libs/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
        <script src="assets/libs/bootstrap-maxlength/bootstrap-maxlength.min.js"></script>
        <script src="assets/libs/clockpicker/bootstrap-clockpicker.min.js"></script>
        <script src="assets/libs/datatables/jquery.dataTables.min.js"></script>
        <script src="assets/libs/datatables/dataTables.bootstrap4.js"></script>
        <script src="assets/libs/datatables/dataTables.responsive.min.js"></script>
        <script src="assets/libs/datatables/responsive.bootstrap4.min.js"></script>
        <script src="assets/libs/datatables/dataTables.buttons.min.js"></script>
        <script src="assets/libs/datatables/buttons.bootstrap4.min.js"></script>
        <script src="assets/libs/datatables/buttons.html5.min.js"></script>
        <script src="assets/libs/datatables/buttons.flash.min.js"></script>
        <script src="assets/libs/datatables/buttons.print.min.js"></script>
        <script src="assets/libs/datatables/dataTables.keyTable.min.js"></script>
        <script src="assets/libs/datatables/dataTables.select.min.js"></script>
        <script src="assets/libs/moment/moment.min.js"></script>
        <script src="assets/libs/daterangepicker/daterangepicker.js"></script>
        <script src="assets/libs/twitter-bootstrap-wizard/jquery.bootstrap.wizard.min.js"></script>
        <script src="assets/libs/treeview/jstree.min.js"></script>
        <script src="assets/js/pages/treeview.init.js"></script>
        <script src="assets/js/pages/form-wizard.init.js"></script>
        <script src="assets/libs/parsleyjs/parsley.min.js"></script>
        <script src="assets/js/app.min.js"></script>
        <style>
            .daterangepicker select.ampmselect,.daterangepicker select.hourselect,.daterangepicker select.minuteselect,.daterangepicker select.secondselect{
                background:#fff;
                border:1px solid #fff;
                color:rgb(0, 0, 0)
            }
        </style>

        
        <script>
        var swObjs = {};
        <?php if (isset($rUser)) { ?>
        var rBouquets = <?=$rUser["bouquet"];?>;
        <?php } else { ?>
        var rBouquets = [];
        <?php } ?>
        
        (function($) {
          $.fn.inputFilter = function(inputFilter) {
            return this.on("input keydown keyup mousedown mouseup select contextmenu drop", function() {
              if (inputFilter(this.value)) {
                this.oldValue = this.value;
                this.oldSelectionStart = this.selectionStart;
                this.oldSelectionEnd = this.selectionEnd;
              } else if (this.hasOwnProperty("oldValue")) {
                this.value = this.oldValue;
                this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
              }
            });
          };
        }(jQuery));
        
        function toggleBouquets() {
            $("#datatable-bouquets tr").each(function() {
                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selectedfilter').removeClass('ui-selected').removeClass("selected");
                    if ($(this).find("td:eq(0)").html()) {
                        window.rBouquets.splice(parseInt($.inArray($(this).find("td:eq(0)").html()), window.rBouquets), 1);
                    }
                } else {            
                    $(this).addClass('selectedfilter').addClass('ui-selected').addClass("selected");
                    if ($(this).find("td:eq(0)").html()) {
                        window.rBouquets.push(parseInt($(this).find("td:eq(0)").html()));
                    }
                }
            });
        }
        function isValidDate(dateString) {
              var regEx = /^\d{4}-\d{2}-\d{2}$/;
              if(!dateString.match(regEx)) return false;  // Invalid format
              var d = new Date(dateString);
              var dNum = d.getTime();
              if(!dNum && dNum !== 0) return false; // NaN value, Invalid date
              return d.toISOString().slice(0,10) === dateString;
        }
        function isValidIP(rIP) {
            if (/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(rIP)) {
                return true;
            } else {
                return false;
            }
        }
        function evaluateForm() {
            if (($("#is_mag").is(":checked")) || ($("#is_e2").is(":checked"))) {
                if ($("#is_mag").is(":checked")) {
                    <?php if (hasPermissions("adv", "add_mag")) { ?>
                    $("#mac_entry_mag").show();
                    window.swObjs["lock_device"].enable();
                    <?php }
                    if (hasPermissions("adv", "add_e2")) { ?>
                    window.swObjs["is_e2"].disable();
                    <?php } ?>
                } else {
                    <?php if (hasPermissions("adv", "add_mag")) { ?>
                    $("#mac_entry_e2").show();
                    <?php }
                    if (hasPermissions("adv", "add_e2")) { ?>
                    window.swObjs["is_mag"].disable();
                    window.swObjs["lock_device"].disable();
                    <?php } ?>
                }
            } else {
                <?php if (hasPermissions("adv", "add_e2")) { ?>
                $("#mac_entry_e2").hide();
                window.swObjs["is_e2"].enable();
                <?php }
                if (hasPermissions("adv", "add_mag")) { ?>
                $("#mac_entry_mag").hide();
                window.swObjs["is_mag"].enable();
                <?php } ?>
                window.swObjs["lock_device"].disable();
            }
        }
        
        $(document).ready(function() {
            $('select.select2').select2({width: '100%'})
            $(".js-switch").each(function (index, element) {
                var init = new Switchery(element);
                window.swObjs[element.id] = init;
            });
            <?php if (hasPermissions("adv", "edit_user") && (!empty($_GET["id"]))) {
              $startDate = "startDate: '" . date("Y-m-d H:i:s", $rUser["exp_date"]) . "'";
            } else {
              $startDate = "startDate: '" . date('Y-m-d H:i:s') . "'";
            }
             ?>
            $('#exp_date').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                timePicker24Hour: true,
                timePicker: true,
                <?php echo $startDate; ?>,
                endDate: moment().startOf('hour').add(32, 'hour'),
                minDate: new Date(),
                locale: {
                    format: 'YYYY-MM-DD HH:mm'
                }
            });
            
            $("#datatable-bouquets").DataTable({
                columnDefs: [
                    {"className": "dt-center", "targets": [0,2,3]}
                ],
                "rowCallback": function(row, data) {
                    if ($.inArray(data[0], window.rBouquets) !== -1) {
                        $(row).addClass("selected");
                    }
                },
                paging: false,
                bInfo: false,
                searching: false
            });
            $("#datatable-bouquets").selectable({
                filter: 'tr',
                selected: function (event, ui) {
                    if ($(ui.selected).hasClass('selectedfilter')) {
                        $(ui.selected).removeClass('selectedfilter').removeClass('ui-selected').removeClass("selected");
                        window.rBouquets.splice(parseInt($.inArray($(ui.selected).find("td:eq(0)").html()), window.rBouquets), 1);
                    } else {            
                        $(ui.selected).addClass('selectedfilter').addClass('ui-selected').addClass("selected");
                        window.rBouquets.push(parseInt($(ui.selected).find("td:eq(0)").html()));
                    }
                }
            });
            
            $("#no_expire").change(function() {
                if ($(this).prop("checked")) {
                    $("#exp_date").prop("disabled", true);
                } else {
                    $("#exp_date").removeAttr("disabled");
                }
            });
            
            $(".js-switch").on("change" , function() {
                evaluateForm();
            });
            
            $("#user_form").submit(function(e){
                var rBouquets = [];
                $("#datatable-bouquets tr.selected").each(function() {
                    rBouquets.push($(this).find("td:eq(0)").html());
                });
                $("#bouquets_selected").val(JSON.stringify(rBouquets));
                $("#allowed_ua option").prop('selected', true);
                $("#allowed_ips option").prop('selected', true);
            });
            $(document).keypress(function (e) {
                if(e.which == 13 && e.target.nodeName != "TEXTAREA") return false;
            });
            $("#add_ip").click(function() {
                if (($("#ip_field").val().length > 0) && (isValidIP($("#ip_field").val()))) {
                    var o = new Option($("#ip_field").val(), $("#ip_field").val());
                    $("#allowed_ips").append(o);
                    $("#ip_field").val("");
                } else {
                    $.toast("<?=$_["please_enter_a_valid_ip_address"]?>");
                }
            });
            $("#remove_ip").click(function() {
                $('#allowed_ips option:selected').remove();
            });
            $("#add_ua").click(function() {
                if ($("#ua_field").val().length > 0) {
                    var o = new Option($("#ua_field").val(), $("#ua_field").val());
                    $("#allowed_ua").append(o);
                    $("#ua_field").val("");
                } else {
                    $.toast("<?=$_["please_enter_a_user_agent"]?>");
                }
            });
            $("#remove_ua").click(function() {
                $('#allowed_ua option:selected').remove();
            });
            $("#max_connections").inputFilter(function(value) { return /^\d*$/.test(value); });
            $("form").attr('autocomplete', 'off');
            
            evaluateForm();
        });
        </script>
    </body>
</html>