<?php
/**
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 * Date: 28.12.2016
 * Time: 20:32
 */

namespace yanpapayan\incubator;

/**
 * Class FormattedException
 * @package yanpapayan\incubator
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 */
class FormattedException extends \Exception
{
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        return parent::__construct(Api::formatResponse($message), $code, $previous);
    }
}