<?php
// backend/modules/wallet/WalletController.php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class WalletController {

    private $db;

    public function __construct(){
        $this->db = Database::getConnection();
    }

    /* =========================================
       GET BALANCE (ledger based)
    ========================================= */
    public function balance(){

        $user = Auth::check();

        $stmt = $this->db->prepare("
            SELECT IFNULL(SUM(
                CASE WHEN type='credit' THEN amount ELSE -amount END
            ),0) AS balance
            FROM wallet_transactions
            WHERE user_id=?
        ");

        $stmt->execute([$user["id"]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success("Wallet balance",[
            "balance" => floatval($row["balance"])
        ]);
    }


    /* =========================================
       ADD MONEY (real payment success)
    ========================================= */
    public function addMoney(){

        $user = Auth::check();

        $data=json_decode(file_get_contents("php://input"),true);

        $amount=floatval($data["amount"] ?? 0);
        $reference=$data["reference"] ?? "manual";

        if($amount<=0){
            Response::error("Invalid amount");
        }

        $stmt=$this->db->prepare("
            INSERT INTO wallet_transactions
            (user_id,type,amount,reference)
            VALUES(?, 'credit', ?, ?)
        ");

        if($stmt->execute([$user["id"],$amount,$reference])){
            Response::success("Money added to wallet");
        }else{
            Response::serverError("Failed to add money");
        }
    }


    /* =========================================
       FAKE ADD COINS (FOR YOUR DASHBOARD POPUP)
       THIS IS THE ONE YOUR HTML CALLS
    ========================================= */
    public function fakeAddCoins(){

        $user = Auth::check();

        $data=json_decode(file_get_contents("php://input"),true);
        $amount=floatval($data["amount"] ?? 0);

        if($amount<=0){
            Response::error("Invalid amount");
        }

        try{

            $this->db->beginTransaction();

            $stmt=$this->db->prepare("
                INSERT INTO wallet_transactions
                (user_id,type,amount,reference)
                VALUES(?, 'credit', ?, 'fake_payment')
            ");

            $stmt->execute([$user["id"],$amount]);

            $this->db->commit();

            Response::success("Coins added successfully");

        }catch(Exception $e){

            $this->db->rollBack();
            Response::serverError("Failed to add coins");

        }
    }


    /* =========================================
       SAFE DEDUCT MONEY (USED FOR PURCHASES)
    ========================================= */
    public function deductMoney($user_id,$amount,$reference="purchase"){

        $stmt=$this->db->prepare("
            SELECT IFNULL(SUM(
                CASE WHEN type='credit' THEN amount ELSE -amount END
            ),0) AS balance
            FROM wallet_transactions
            WHERE user_id=?
        ");

        $stmt->execute([$user_id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);

        if($row["balance"] < $amount){
            return false; // insufficient funds
        }

        $stmt2=$this->db->prepare("
            INSERT INTO wallet_transactions
            (user_id,type,amount,reference)
            VALUES(?, 'debit', ?, ?)
        ");

        return $stmt2->execute([$user_id,$amount,$reference]);
    }


    /* =========================================
       WALLET HISTORY
    ========================================= */
    public function history(){

        $user = Auth::check();

        $stmt=$this->db->prepare("
            SELECT id,type,amount,reference,created_at
            FROM wallet_transactions
            WHERE user_id=?
            ORDER BY id DESC
        ");

        $stmt->execute([$user["id"]]);

        Response::success("Wallet history",$stmt->fetchAll(PDO::FETCH_ASSOC));
    }

}