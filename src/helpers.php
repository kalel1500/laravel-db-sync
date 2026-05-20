<?php

declare(strict_types=1);

if (! function_exists('null_if_empty')) {
    function null_if_empty($value)
    {
        return (trim($value) === '') ? null : $value;
    }
}
