<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../derekdnd.php");

// only functions within this class can be called by ajax
class ajax {

    function setPanTilt() {
        global $maindb;
        global $cameraName;

        $s_pan = get_post_var("pan");
        $s_tilt = get_post_var("tilt");
        $s_camera = get_post_var("camera");
        $i_clientId = intval(get_post_var("clientId"));
        $i_message_idx = intval(get_post_var("message_idx"));
        $i_pan = intval($s_pan);
        $i_tilt = intval($s_tilt);
        
        // validate parameters
        if ($s_pan == "" || $s_tilt == "" || $s_camera == "")
            return "\"pan\", \"tilt\", and \"camera\" post variables must be set!";
        if ($s_camera != $cameraName)
            return "\"camera\" post variable must match selected camera name!";
        $where = ["name"=>"${cameraName}"];
        $a_rows = db_query("SELECT `id`,`pan_range`,`tilt_range` FROM `${maindb}`.`cameras` WHERE " . array_to_where_clause($where), $where);
        if (!is_array($a_rows) || sizeof($a_rows) == 0)
            return "Could not find camera with name \"${s_camera}";
        $i_panRange = intval($a_rows[0]["pan_range"]);
        $i_tiltRange = intval($a_rows[0]["tilt_range"]);
        if ($i_pan < -$i_panRange || $i_pan > $i_panRange || $i_tilt < -$i_tiltRange || $i_tilt > $i_tiltRange)
            return "\"pan\" and \"tilt\" must be between +-${i_panRange} and ${i_tiltRange}, respectively";

        // update the database
        $ab_success = setPanAndTilt($i_pan, $i_tilt, $i_message_idx, FALSE, $s_camera);
        if ($ab_success === FALSE)
            return "failed to update database";

        // let all the other clients know
        try
        {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if(is_resource($socket)) {
                if (socket_connect($socket, "127.0.0.1", 12345)) {
                    $a_camera = getVals($s_camera);
                    $a_camera['camera'] = $s_camera;
                    $a_camera['clientId'] = $i_clientId;
                    $a_camera['message_idx'] = $i_message_idx;
                    $s_encoded = json_encode($a_camera);
                    socket_write($socket, "subscribe ${s_encoded}\n"); // used to register this client with the camera name
                    socket_write($socket, "${s_encoded}\n");
                    socket_write($socket, "disconnect\n");
                } else {
                    error_log("Failed to connect to 127.0.0.1:12345 to propogate message");
                }
            } else {
                error_log("Failed to create socket to propogate message");
            }
        }
        finally
        {
            return "success";
        }
    }

    function getPanTilt() {
        global $maindb;
        global $cameraName;

        $s_camera = get_post_var("camera");
        
        // validate parameters
        if ($s_camera != $cameraName)
            return "\"camera\" post variable must match selected camera name!";
        $a_camera = getVals($s_camera);

        // return camera values
        return json_encode($a_camera);
    }

    function subscribePanTilt() {
        global $maindb;
        global $cameraName;

        $s_camera = get_post_var("camera");
        $i_clientId = intval(get_post_var("clientId"));
        $i_message_idx = intval(get_post_var("lastMessageId"));
        
        // validate parameters
        if ($s_camera != $cameraName)
            return "\"camera\" post variable must match selected camera name!";
        $where = ["name"=>"${cameraName}"];
        $a_rows = db_query("SELECT `id`,`pan_range`,`tilt_range` FROM `${maindb}`.`cameras` WHERE " . array_to_where_clause($where), $where);
        if (!is_array($a_rows) || sizeof($a_rows) == 0)
            return "Could not find camera with name \"${s_camera}";

        // listen for the next camera value update
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 12345)) {
                $s_encoded = json_encode([ "camera"=>$s_camera, "message_idx"=>$i_message_idx ]);
                socket_write($socket, "subscribe ${s_encoded}\n");
                socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>60, "usec"=>0));
                while (true) {
                    $s_ret = socket_read($socket, 2048);
                    $i_newlinePos = strpos($s_ret, "\n");
                    if ($i_newlinePos !== FALSE)
                        $s_ret = substr($s_ret, 0, $i_newlinePos);
                    $a_ret = json_decode($s_ret, TRUE);
                    if (intval($a_ret['clientId']) != $i_clientId) {
                        socket_write($socket, "disconnect\n");
                        return $s_ret;
                    }
                }
            } else {
                error_log("Failed to connect to 127.0.0.1:12345 to propogate message");
            }
        } else {
            error_log("Failed to create socket to propogate message");
        }
    }

}

$s_command = get_post_var("command");

if ($s_command != '') {
    $o_ajax = new ajax();
    if (method_exists($o_ajax, $s_command)) {
        echo $o_ajax->$s_command();
    } else {
        echo "bad \"command\" post var";
    }
} else {
    echo "missing \"command\" post var";
}

?>