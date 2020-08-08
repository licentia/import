<?php
/*
 * Copyright (C) Licentia, Unipessoal LDA
 *
 * NOTICE OF LICENSE
 *
 *  This source file is subject to the EULA
 *  that is bundled with this package in the file LICENSE.txt.
 *  It is also available through the world-wide-web at this URL:
 *  https://www.greenflyingpanda.com/panda-license.txt
 *
 *  @title      Licentia Panda - Magento® Sales Automation Extension
 *  @package    Licentia
 *  @author     Bento Vilas Boas <bento@licentia.pt>
 *  @copyright  Copyright (c) Licentia - https://licentia.pt
 *  @license    https://www.greenflyingpanda.com/panda-license.txt
 *
 */

namespace Licentia\Import\Ui\Component\Listing\Column;

use Licentia\Import\Model\CronSchedule;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class Number
 *
 * @package Licentia\Panda\Ui\Component\Listing\Column\Reports
 */
class Cron extends Column
{

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {

                $cron = CronSchedule::fromCronString($item['cron_expression']);

                $item[$this->getData('name')] = $cron->asNaturalLanguage();
            }
        }

        return $dataSource;
    }
}
