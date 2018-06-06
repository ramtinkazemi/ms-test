<?php
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;
if (! function_exists('array_orderby')) {
    /**
     * ref : http://www.php.net/manual/en/function.array-multisort.php#100534
     * example : $sortec = array_orderby($data, 'volume', SORT_DESC, 'edition', SORT_ASC);
     *
     * @return mixed
     */
    function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }
}

if (! function_exists('array_column_sum')) {
    /**
     * @param $input
     * @param $column_key
     * @return float|int
     */
    function array_column_sum($input, $column_key)
    {
        return array_sum(array_column($input, $column_key));
    }
}

if (! function_exists('color_dump')) {

    /**
     *
     * ref : (new CliDumper())->dump((new VarCloner)->cloneVar($value));
     * example : color_dump($your_arr);
     * @TODO: It needs option not to truncate data.
     *
     * @param array ...$args
     */
    function color_dump(...$args)
    {
        foreach ($args as $x) {
            if (class_exists(CliDumper::class)) {
                $dumper = 'cli' === PHP_SAPI ? new CliDumper : new HtmlDumper;

                $cloner = new VarCloner();
                $dumper->dump($cloner->cloneVar($x));
            } else {
                var_dump($x);
            }
        }
    }
}

if (! function_exists('echo_ex')) {
    /**
     *
     * example : echo_ex('<info>'.'TEST'.'</info>');
     * @param array ...$args
     */
    function echo_ex(...$args)
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE, true);
        //$style = new OutputFormatterStyle('red', 'yellow', array('bold', 'blink'));
        //$output->getFormatter()->setStyle('fire', $style);

        foreach ($args as $x) {
            //$output->writeln('<info>'.'TEST'.'</info>');
            $output->writeln($x);

        }
        echo $output->fetch();
    }
}
