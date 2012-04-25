<?php

namespace PhpManual;

use PhpManual\Doc\LanguageRef;
use PhpManual\Doc\SearchSuggestions;
use PhpManual\Doc\FunctionRef;
use PhpManual\Doc\ClassRef;

class PhpManual
{
    
    public function fromData($data)
    {    
        $dOM = new \DOMDocument;
        @$dOM->loadHTML($data);
        $contents = $dOM
            ->getElementsByTagName('body')->item(0)
            ->getElementsByTagName('div')->item(3)
            ->getElementsByTagName('div')->item(1)
            ->getElementsByTagName('div')->item(2);
        if ($contents){
            $pageId = $contents->getAttribute('id');
            $pageType = $this->_determinePageType($pageId);
            var_dump($pageType);
            switch ($pageType) {
                case 'function':
                    $manualPage = new FunctionRef;
                    $infoDiv = $contents->getElementsByTagName('div')->item(0);
                    $manualPage->phpFunction = $infoDiv
                        ->getElementsByTagName('h1')->item(0)->nodeValue;
                    $paragraphs = $infoDiv->getElementsByTagName('p');
                    foreach ($paragraphs as $paragraph) {
                        $class = $paragraph->getAttribute('class');
                        switch ($class) {
                            case 'verinfo':
                                $manualPage->phpVersion = $this
                                    ->_parsePhpVersion($paragraph->nodeValue);
                                break;
                            case 'refpurpose':
                                $manualPage->functionDescription = $this
                                    ->_removeNewlines(
                                        $paragraph->getElementsByTagName('span')
                                        ->item(1)->nodeValue
                                    );
                                break;
                        }
                    }
                    $descriptionDiv = $contents->getElementsByTagName('div')
                        ->item(1);
                    if ($descriptionDiv->getElementsByTagName('div')->item(0)) {
                        $functionSyntax = $this->_parseFunctionSyntax(
                            $descriptionDiv->getElementsByTagName('div')
                            ->item(0)->nodeValue
                        );
                    }
                    if (isset($functionSyntax)) {
                        $manualPage->functionSyntax = $functionSyntax[1];
                        $manualPage->functionReturnType = $functionSyntax[0];
                        $manualPage->prototype = $manualPage->functionReturnType
                            . ' ' . $manualPage->functionSyntax;
                    }
                    return $manualPage;
                case 'class':
                    $manualPage = new ClassRef;
                    $manualPage->pageTitle = $contents
                        ->getElementsByTagName('h1')->item(0)->nodeValue;
                    $intro = $contents->getElementsByTagName('div')->item(0)
                        ->getElementsByTagName('div')->item(0);
                    $description = $intro->getElementsByTagName('p')->item(0);
                    if (!empty($description)) {
                        $manualPage->description = $this->_removeNewlines(
                            $description->nodeValue
                        );
                        $synopsisId = 1;
                    }
                    else {
                        $synopsisId = 0;
                    }
                    $synopsis = $contents->getElementsByTagName('div')->item(0)
                        ->getElementsByTagName('div')->item($synopsisId)
                        ->getElementsByTagName('div')->item(0);
                    $manualPage->name = $this->_removeNewlines(
                        $synopsis->getElementsByTagName('div')->item(1)
                            ->getElementsByTagName('span')->item(0)->nodeValue
                    );
                    $totalDivs = $synopsis->getElementsByTagName('div')->length;
                    for ($i = 0; $i < $totalDivs; ++$i) {
                        $div = $synopsis->getElementsByTagName('div')->item($i);
                        $totalSpans = $div->getElementsByTagName('span')->length;
                        for ($ii = 0; $ii < $totalSpans; ++$ii) {
                            $span = $div->getElementsByTagName('span')
                                ->item($ii);
                            if ($span->getAttribute('class') == 'methodname'
                                && strstr($span->nodeValue, '__construct')
                            ) {
                                $foundConstruct = true;
                                break;
                            }
                        }
                        if (isset($foundConstruct)) {
                            $manualPage->constructSyntax = $this
                                ->_removeNewlines($div->nodeValue);
                            break;
                        }
                    }
                    return $manualPage;
                case 'book':
                    return false;
                case 'language':
                    $manualPage = new LanguageRef;
                    $title = $contents->getElementsByTagName('h1')->item(0);
                    if ($title) {
                        $manualPage->title = $title->nodeValue;
                    } else {
                        $manualPage->title = $contents
                            ->getElementsByTagName('h2')->item(0)->nodeValue;
                    }
                    $manualPage->description = $this->_removeNewlines(
                        $contents->getElementsByTagName('p')->item(0)->nodeValue
                    );
                    return $manualPage;
            }
        }
        else {
            $parsedSuggestions = new SearchSuggestions;
            $suggestions = $dOM->getElementsByTagName('ul')->item(0)
                ->getElementsByTagName('li');
            for ($i = 0; $i < $suggestions->length; ++$i) {
                $suggestion = $suggestions->item($i);
                if ($suggestion->getElementsByTagName('b')->length) {
                    $parsedSuggestions->bestSuggestions[] = $suggestion
                        ->nodeValue;
                }
                else {
                    $parsedSuggestions->suggestions[] = $suggestion->nodeValue;
                }
            }
            return $parsedSuggestions;
        }
    }
    private function _parsePhpVersion($versionStr) {
        return substr($versionStr, 1, -1);
    }
    private function _parseFunctionSyntax($syntaxStr) {
        $syntaxStr = trim(preg_replace("/[\n\r]/", null, $syntaxStr));
        $syntaxStrExp = explode(' ', $syntaxStr);
        $returnType = $syntaxStrExp[0];
        unset($syntaxStrExp[0]);
        foreach ($syntaxStrExp as $key => $value) {
            if (empty($value)) {
                unset($syntaxStrExp[$key]);
            }
        }
        $syntaxStr = implode(' ', $syntaxStrExp);
        return array($returnType, $syntaxStr);
    }
    private function _determinePageType($pageId) {
        list($pageType) = explode('.', $pageId, 2); 
        return $pageType;
    }
    private function _removeNewlines($string) {
        $string = trim(preg_replace("/[\n\r]/", null, $string));
        $string = explode(' ', $string);
        foreach ($string as $key => $value) {
            if (empty($value)) {
                unset($string[$key]);
            }
        }
        return implode(' ', $string);
    }
    
}

if ($_SERVER['PHP_SELF'] == basename(__FILE__)) {
    echo 'Initializing...'. PHP_EOL;
    include_once 'Doc/FunctionRef.php';
    include_once 'Doc/ClassRef.php';
    include_once 'Doc/LanguageRef.php';
    include_once 'Doc/SearchSuggestions.php';
    
    array_shift($_SERVER['argv']);
    
    $searchTerm = implode(' ', $_SERVER['argv']);
    $phpManual = new PhpManual;
    echo 'Looking up: ' . $searchTerm . PHP_EOL;
    $response = $phpManual->fromData(
        file_get_contents('http://nl3.php.net/' . urlencode($searchTerm))
    );
    print_r($response);
    if ($response instanceof SearchSuggestions) {
        echo 'Sorry could not find anything. Did you mean '
            . $response->bestSuggestions[0] . '?' . PHP_EOL;
    }
}
?>
