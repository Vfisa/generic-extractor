<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException;
use Keboola\Code\Builder,
    Keboola\Code\Exception\UserScriptException;
use Keboola\Utils\Utils,
    Keboola\Utils\Exception\JsonDecodeException;
/**
 * Keboola\Code\Builder wrapper
 */
class UserFunction
{
    /**
     * @param array|\stdClass $functions
     * @param array $params ['attr' => $attributesArray, ...]
     * @param Builder $builder
     * @return array
     */
    public static function build($functions, array $params = [], Builder $builder = null)
    {
        if (is_null($builder)) {
            $builder = new Builder();
        }

        $functions = (array) Utils::arrayToObject($functions);
        try {
            array_walk($functions, function(&$value, $key) use ($params, $builder) {
                $value = !is_object($value) ? $value : $builder->run($value, $params);
            });
        } catch(UserScriptException $e) {
            throw new UserException('User script error: ' . $e->getMessage());
        }

        return $functions;
    }
}
