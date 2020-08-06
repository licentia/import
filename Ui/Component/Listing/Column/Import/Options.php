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

namespace Licentia\Import\Ui\Component\Listing\Column\Import;

use Licentia\Import\Model\ImportFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Options
 */
class Options implements OptionSourceInterface
{

    /**
     * @var importFactory
     */
    protected $importFactory;

    /**
     * Options constructor.
     *
     * @param importFactory $importFactory
     */
    public function __construct(ImportFactory $importFactory
    ) {

        $this->importFactory = $importFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {

        return $this->importFactory->create()->toOptionArray();
    }
}
