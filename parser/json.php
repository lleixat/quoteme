<?php
class jsonParser
extends parser
implements parserTemplate
{
    public function parse($elements)
    {
        $i = 0;
        if (is_array($elements)) {
            foreach ($elements as $value) {
                $quote[$i]['text']   = $value->getText();
                $quote[$i]['author'] = $value->getAuthor();
                $quote[$i]['source'] = $value->getSource();
                $i++;
            }
        }
        $result = json_encode($quote);
        return $result;
    }
}
?>