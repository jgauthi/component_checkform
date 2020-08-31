<?php
/**
 * @param string|int|float|null $content
 */
function sql_data($content): string
{
    if (empty($content))
            return ('NULL');
    else	return ('\''. addSlashes(trim($content)) .'\'');
}