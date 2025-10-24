<?php

namespace DSAllround\Views;
use Exception;

class Contact extends Page
{
    /**
     * Properties
     */

    /**
     * Instantiates members (to be defined above).
     * Calls the constructor of the parent i.e. page class.
     * So, the database connection is established.
     * @throws Exception
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Cleans up whatever is needed.
     * Calls the destructor of the parent i.e. page class.
     * So, the database connection is closed.
     */
    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * This main-function has the only purpose to create an instance
     * of the class and to get all the things going.
     * I.e. the operations of the class are called to produce
     * the output of the HTML-file.
     * The name "main" is no keyword for php. It is just used to
     * indicate that function as the central starting point.
     * To make it simpler this is a static function. That is you can simply
     * call it without first creating an instance of the class.
     * @return void
     */
    public static function main():void
    {
        // Generiere ein zufälliges Token und speichere es in der Sitzung
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        try {
            $page = new Contact();
            $page->generateView();
        } catch (Exception $e) {
            //header("Content-type: text/plain; charset=UTF-8");
            header("Content-type: text/html; charset=UTF-8");
            echo $e->getMessage();
        }
    }

    /**
     * First the required data is fetched and then the HTML is
     * assembled for output. i.e. the header is generated, the content
     * of the page ("view") is inserted and -if available- the content of
     * all views contained is generated.
     * Finally, the footer is added.
     * @return void
     */
    protected function generateView():void
    {
        //$this->generatePageHeader('Dionysos'); //to do: set optional parameters

        //$this->generateNav();
        $this->generateMainBody();
        //$this->generatePageFooter();
    }

    private function generateMainBody(){
        $this->generateIntroDisplay();
        $this->generateBody();
    }

    protected function additionalMetaData() :void
    {
        //Links for css or js

        // Cookie Handler
        if($this->cookieHandler->isAllowGoogle()){
            echo <<< EOT
            <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cinzel&family=Open+Sans+Condensed:wght@300&display=swap" rel="stylesheet"/>
            EOT;
        }
    }

    private function generateIntroDisplay(){
        echo <<< EOT
            <div>
                <h1>Kontakt</h1>
            </div>
        EOT;
    }

    private function generateBody(){
    }
}
Contact::main();