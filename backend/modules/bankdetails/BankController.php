<?php
// backend/modules/bankdetails/BankController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class BankController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /* =========================
       ADD / UPDATE BANK DETAILS
       (MATCHES YOUR FRONTEND)
    ========================= */
    public function save(){

        $user = Auth::check();

        $data=json_decode(file_get_contents("php://input"),true);

        // ⭐ MATCH YOUR HTML FIELD NAMES
        $holder_name   = trim($data["holder_name"] ?? "");
        $bank_name     = trim($data["bank_name"] ?? "");
        $account_number= trim($data["account_number"] ?? "");
        $ifsc          = trim($data["ifsc"] ?? "");

        if(!$holder_name || !$bank_name || !$account_number || !$ifsc){
            Response::error("Missing bank fields");
        }

        /* BASIC VALIDATION */

        if(strlen($account_number) < 6){
            Response::error("Invalid account number");
        }

        if(strlen($ifsc) < 5){
            Response::error("Invalid IFSC");
        }

        /* CHECK EXISTING */

        $check=$this->db->prepare("
            SELECT id FROM bank_details WHERE user_id=?
        ");
        $check->execute([$user["id"]]);

        if($check->rowCount()>0){

            $this->db->prepare("
                UPDATE bank_details
                SET holder_name=?, bank_name=?, account_number=?, ifsc=?
                WHERE user_id=?
            ")->execute([
                $holder_name,
                $bank_name,
                $account_number,
                $ifsc,
                $user["id"]
            ]);

            Response::success("Bank details updated");

        }else{

            $this->db->prepare("
                INSERT INTO bank_details
                (user_id,holder_name,bank_name,account_number,ifsc)
                VALUES(?,?,?,?,?)
            ")->execute([
                $user["id"],
                $holder_name,
                $bank_name,
                $account_number,
                $ifsc
            ]);

            Response::success("Bank details saved");
        }
    }


    /* =========================
       VIEW BANK DETAILS
    ========================= */
    public function view(){

        $user=Auth::check();

        $stmt=$this->db->prepare("
            SELECT holder_name,bank_name,account_number,ifsc
            FROM bank_details
            WHERE user_id=?
        ");

        $stmt->execute([$user["id"]]);

        $bank=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$bank){
            Response::success("No bank yet",null);
            return;
        }

        Response::success("Bank details",$bank);
    }

}