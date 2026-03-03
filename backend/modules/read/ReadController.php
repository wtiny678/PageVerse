<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class ReadController {

    private $db;

    public function __construct(){
        $this->db = Database::getConnection();
    }

    /* =====================================
       GET TOKEN (HEADER OR QUERY)
    ===================================== */

    private function getToken(){

        $headers = getallheaders();

        if(isset($headers["Authorization"])){
            if(preg_match('/Bearer\s(\S+)/',$headers["Authorization"],$m)){
                return $m[1];
            }
        }

        return $_GET["token"] ?? null;
    }


    /* =====================================
       READ CHAPTER
    ===================================== */

    public function readChapter(){

        $user = Auth::check($this->getToken());

        $chapter_id = $_GET["chapter_id"] ?? null;

        if(!is_numeric($chapter_id)){
            Response::error("Chapter ID missing");
        }

        $stmt=$this->db->prepare("
            SELECT c.id,c.title,c.content,c.price,c.book_id,
                   b.user_id AS author_id
            FROM chapters c
            JOIN books b ON c.book_id=b.id
            WHERE c.id=?
        ");

        $stmt->execute([$chapter_id]);
        $chapter=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$chapter){
            Response::error("Chapter not found");
        }

        /* OWNER FREE */
        if($chapter["author_id"]==$user["id"]){
            Response::success("Chapter loaded",$chapter);
        }

        /* CHECK PURCHASE */
        $check=$this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id=? AND chapter_id=?
        ");

        $check->execute([$user["id"],$chapter_id]);

        if($check->rowCount()==0){
            Response::error("Please purchase this chapter to read");
        }

        Response::success("Chapter loaded",$chapter);
    }


    /* =====================================
       STREAM PDF / EPUB / FILE
    ===================================== */

    public function readFile(){

        $user = Auth::check($this->getToken());

        $file_id = $_GET["file_id"] ?? null;

        if(!is_numeric($file_id)){
            Response::error("File ID missing");
        }

        /* GET FILE */

        $stmt = $this->db->prepare("
            SELECT ub.*, b.user_id AS author_id
            FROM uploaded_books ub
            JOIN books b ON ub.book_id = b.id
            WHERE ub.id = ?
            LIMIT 1
        ");

        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$file){
            Response::error("File not found in DB");
        }

        /* =============================
           PURCHASE CHECK (NOT OWNER)
           FIXED VERSION
        ============================= */

        if($file["author_id"] != $user["id"]){

            $check = $this->db->prepare("
                SELECT id FROM purchases
                WHERE user_id=? 
                AND book_id=? 
                AND (chapter_id IS NULL OR chapter_id=0)
            ");

            $check->execute([$user["id"], $file["book_id"]]);

            if($check->rowCount() == 0){
                Response::error("Please purchase this book to read");
            }
        }

        /* =============================
           CORRECT FILE PATH
        ============================= */

        $path = realpath(dirname(__DIR__,2)."/uploads/books/".$file["file_path"]);

        if(!$path || !file_exists($path)){
            Response::error("File missing on server");
        }

        /* MIME */

        $mime = match(strtolower($file["file_type"])) {
            "pdf"  => "application/pdf",
            "epub" => "application/epub+zip",
            "cbz"  => "application/zip",
            "zip"  => "application/zip",
            default => "application/octet-stream"
        };

        /* STREAM FILE */

        header("Content-Type: $mime");
        header("Content-Disposition: inline; filename=\"".$file["file_name"]."\"");
        header("Content-Length: " . filesize($path));

        readfile($path);
        exit;
    }
}