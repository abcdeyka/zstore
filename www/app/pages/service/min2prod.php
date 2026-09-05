<?php

namespace App\Pages\Service;

use App\Application as App;
use App\Entity\Category;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\ItemSet;
use App\Entity\Store;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Binding\PropertyBinding as Bind;

/**
 * Min2Prod — расчёт количества к производству
 */
class Min2Prod extends \App\Pages\Base
{
    public $_list = [];

    private $_store_id = 0;
    private $_cat_id = 0;
    private $_search = '';

    private $_bonus0 = 0;
    private $_bonus1 = 1;
    private $_bonus2 = 2;
    private $_bonus3 = 5;

    public function __construct()
    {
        parent::__construct();

        $this->add(new Form('searchform'));

        $this->searchform->add(
            new DropDownChoice(
                'store',
                Store::getList(),
                H::getDefStore()
            )
        );

        $this->searchform->add(
            new DropDownChoice(
                'category',
                Category::findArray(
                    'cat_name',
                    '',
                    'cat_name'
                ),
                0
            )
        );

        $this->searchform->add(
            new TextInput('search')
        );

        /*
         * Бонусы продаж
         *
         * 0      = bonus0
         * 1-3    = bonus1
         * 4-9    = bonus2
         * 10+    = bonus3
         */
        $this->searchform->add(
            new TextInput('bonus0')
        )->setText('0');

        $this->searchform->add(
            new TextInput('bonus1')
        )->setText('1');

        $this->searchform->add(
            new TextInput('bonus2')
        )->setText('2');

        $this->searchform->add(
            new TextInput('bonus3')
        )->setText('5');

        $this->searchform
            ->add(new SubmitButton('update'))
            ->onClick($this, 'onUpdate');

        /*
         * Таблица
         */
        $this->add(new Form('listform'));

        $this->listform->add(
            new DataView(
                'list',
                new ArrayDataSource(
                    new Bind($this, '_list')
                ),
                $this,
                'listOnRow'
            )
        );

        $this->listform
            ->add(new SubmitButton('export'))
            ->onClick($this, 'onExport');

        $this->listform
            ->add(new ClickLink('selectall'))
            ->onClick($this, 'onSelectAll');

        $this->listform
            ->add(new ClickLink('deselectall'))
            ->onClick($this, 'onDeselectAll');

        /*
         * Первичная загрузка
         */
        $this->onUpdate(null);
    }

    /**
     * Обновление списка
     */
    public function onUpdate($sender)
    {
        $this->_store_id =
            (int)$this->searchform->store->getValue();

        $this->_cat_id =
            (int)$this->searchform->category->getValue();

        $this->_search =
            trim(
                $this->searchform->search->getText()
            );

        /*
         * Получаем актуальные бонусы.
         */
        $this->_bonus0 =
            max(
                0,
                (int)$this->searchform->bonus0->getText()
            );

        $this->_bonus1 =
            max(
                0,
                (int)$this->searchform->bonus1->getText()
            );

        $this->_bonus2 =
            max(
                0,
                (int)$this->searchform->bonus2->getText()
            );

        $this->_bonus3 =
            max(
                0,
                (int)$this->searchform->bonus3->getText()
            );

        if ($this->_store_id <= 0) {

            $this->setError(
                'Выберите склад'
            );

            $this->_list = [];

            $this->listform->list->Reload();

            return;
        }

        $this->updatelist();

        $this->listform->list->Reload();
    }

    /**
     * Выбрать всё
     */
    public function onSelectAll($sender)
    {
        foreach ($this->_list as $row) {
            $row->selected = true;
        }

        $this->listform->list->Reload();
    }

    /**
     * Снять всё
     */
    public function onDeselectAll($sender)
    {
        foreach ($this->_list as $row) {
            $row->selected = false;
        }

        $this->listform->list->Reload();
    }

    /**
     * Формирование списка
     */
    public function updatelist()
    {
        $this->_list = [];

        $store_id =
            $this->_store_id;

        /*
         * Только активная готовая продукция.
         */
        $where =
            "disabled <> 1 AND item_type IN (4, 5)";

        /*
         * Категория.
         */
        if ($this->_cat_id > 0) {

            $where .=
                " AND cat_id = "
                . intval($this->_cat_id);
        }

        /*
         * Поиск по названию или коду.
         */
        if ($this->_search !== '') {

            $s =
                Item::qstr(
                    '%' . $this->_search . '%'
                );

            $where .=
                " AND ("
                . "itemname LIKE {$s}"
                . " OR item_code LIKE {$s}"
                . ")";
        }

        $items =
            Item::find(
                $where,
                'itemname'
            );

        foreach ($items as $item) {

            /*
             * Остаток готовой продукции.
             */
            $qty =
                $this->getStoreQty(
                    $item->item_id,
                    $store_id
                );

            /*
             * minqty из товара.
             */
            $minqty =
                (float)$item->minqty;

            /*
             * recom из cflist.
             */
            $recom =
                $this->getRecom($item);

            /*
             * Если recom есть —
             * он полностью заменяет minqty.
             */
            $effectiveMinQty =
                ($recom > 0)
                    ? $recom
                    : $minqty;

            /*
             * Если эффективный минимум не задан —
             * товар не нужен.
             */
            if ($effectiveMinQty <= 0) {
                continue;
            }

            /*
             * Если готовой продукции уже хватает —
             * товар не показываем.
             */
            if (
                $qty >=
                $effectiveMinQty
            ) {
                continue;
            }

            /*
             * Базовое количество:
             * сколько не хватает до recom/minqty.
             */
            $baseQty =
                $effectiveMinQty - $qty;

            /*
             * Продажи за 7 дней.
             */
            $sales =
                $this->getWeekSales(
                    $item->item_id
                );

            /*
             * Бонус по продажам.
             */
            $bonus =
                $this->salesBonus(
                    $sales
                );

            /*
             * Итог до ограничения комплектующими.
             */
            $prodqty =
                $baseQty + $bonus;

            /*
             * Ограничиваем количество
             * доступными комплектующими.
             *
             * Если какого-либо компонента нет —
             * prodqty будет 0.
             */
            $prodqty =
                $this->limitByItemSet(
                    $item->item_id,
                    $prodqty,
                    $store_id
                );

            /*
             * Только целое количество.
             */
            $prodqty =
                floor($prodqty);

            /*
             * ВАЖНО:
             * здесь НЕ делаем continue при 0.
             *
             * Товар должен остаться в списке,
             * чтобы было видно, что приход невозможен
             * из-за отсутствия компонентов.
             */
            $prodqty =
                max(
                    0,
                    $prodqty
                );

            /*
             * Формируем строку.
             */
            $row =
                new \App\DataItem();

            $row->item_id =
                $item->item_id;

            $row->itemname =
                $item->itemname;

            /*
             * Код оставляем в DataItem,
             * чтобы поиск по коду продолжал работать.
             * В HTML он не выводится.
             */
            $row->item_code =
                $item->item_code;

            $row->msr =
                $item->msr;

            $row->qty =
                $qty;

            $row->minqty =
                $minqty;

            $row->recom =
                $recom;

            $row->effective =
                $effectiveMinQty;

            $row->sales =
                $sales;

            $row->prodqty =
                $prodqty;

            /*
             * Если невозможно произвести ни одной штуки,
             * автоматически снимаем выбор.
             */
            $row->selected =
                ($prodqty > 0);

            $this->_list[
                $item->item_id
            ] = $row;
        }
    }

    /**
     * Остаток товара на выбранном складе.
     */
    private function getStoreQty(
        int $item_id,
        int $store_id
    ): float
    {
        $conn =
            \ZDB\DB::getConnect();

        $sql =
            "SELECT COALESCE(SUM(qty), 0)
             FROM store_stock
             WHERE item_id = "
            . intval($item_id)
            . "
             AND store_id = "
            . intval($store_id);

        return (float)
            $conn->GetOne($sql);
    }

    /**
     * Получение recom из cflist.
     */
    private function getRecom(
        Item $item
    ): float
    {
        $detail =
            (string)$item->detail;

        if ($detail === '') {
            return 0;
        }

        if (
            !preg_match(
                '/<cflist[^>]*>(.*?)<\/cflist>/isu',
                $detail,
                $m
            )
        ) {
            return 0;
        }

        $raw =
            trim(
                html_entity_decode(
                    $m[1],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );

        if (
            $raw === ''
            || $raw === 'a:0:{}'
        ) {
            return 0;
        }

        $data =
            @unserialize($raw);

        /*
         * Если unserialize не сработал,
         * пробуем достать recom регулярным выражением.
         */
        if (
            $data === false
            && $raw !== 'b:0;'
        ) {

            if (
                preg_match(
                    '/s:\d+:"recom";s:\d+:"([^"]*)"/u',
                    $raw,
                    $mm
                )
            ) {

                $val =
                    (float)
                    str_replace(
                        ',',
                        '.',
                        $mm[1]
                    );

                return max(
                    0,
                    $val
                );
            }

            if (
                preg_match(
                    '/s:\d+:"recom";i:(\d+)/u',
                    $raw,
                    $mm
                )
            ) {

                return max(
                    0,
                    (float)$mm[1]
                );
            }

            return 0;
        }

        if (
            !is_array($data)
            || !isset($data['recom'])
        ) {
            return 0;
        }

        $val =
            (float)
            str_replace(
                ',',
                '.',
                (string)$data['recom']
            );

        return max(
            0,
            $val
        );
    }

    /**
     * Продажи за последние 7 дней.
     */
    private function getWeekSales(
        int $item_id
    ): float
    {
        $conn =
            \ZDB\DB::getConnect();

        $from =
            strtotime('-7 days');

        $sql =
            "SELECT COALESCE(SUM(ABS(quantity)), 0)
             FROM entrylist_view
             WHERE item_id = "
            . intval($item_id)
            . "
             AND quantity < 0
             AND document_date >= "
            . $conn->DBDate($from);

        return (float)
            $conn->GetOne($sql);
    }

    /**
     * Бонус продаж:
     *
     * 0       → bonus0
     * 1-3     → bonus1
     * 4-9     → bonus2
     * 10+     → bonus3
     */
    private function salesBonus(
        float $sales
    ): int
    {
        if ($sales <= 0) {
            return $this->_bonus0;
        }

        if ($sales <= 3) {
            return $this->_bonus1;
        }

        if ($sales <= 9) {
            return $this->_bonus2;
        }

        return $this->_bonus3;
    }

    /**
     * Расчёт максимально возможного количества
     * готовой продукции по комплектующим.
     *
     * ItemSet:
     *
     * pitem_id = готовая продукция
     * item_id  = компонент
     * qty      = расход компонента на 1 готовую единицу
     *
     * Если хотя бы одного компонента нет —
     * возвращаем 0.
     */
    private function limitByItemSet(
        int $item_id,
        float $prodqty,
        int $store_id
    ): float
    {
        /*
         * Получаем рецепт именно по pitem_id.
         */
        $parts =
            ItemSet::find(
                "pitem_id = "
                . intval($item_id)
            );

        /*
         * Если рецепта нет —
         * ограничивать нечем.
         */
        if (count($parts) == 0) {
            return $prodqty;
        }

        /*
         * Большое начальное значение.
         */
        $max_possible =
            1000000;

        foreach ($parts as $part) {

            /*
             * ID компонента.
             */
            $part_id =
                (int)$part->item_id;

            /*
             * Сколько компонента нужно
             * на одну единицу готовой продукции.
             */
            $needed =
                (float)$part->qty;

            /*
             * Некорректную строку рецепта
             * пропускаем.
             */
            if (
                $part_id <= 0
                || $needed <= 0
            ) {
                continue;
            }

            /*
             * Загружаем компонент.
             */
            $component =
                Item::load($part_id);

            /*
             * Если компонент не найден,
             * готовую продукцию сделать нельзя.
             */
            if ($component == null) {
                return 0;
            }

            /*
             * Остаток компонента
             * на выбранном складе.
             */
            $remaining =
                $this->getStoreQty(
                    $part_id,
                    $store_id
                );

            /*
             * Если обязательного компонента
             * нет вообще — ничего произвести нельзя.
             */
            if ($remaining <= 0) {
                return 0;
            }

            /*
             * Сколько готовой продукции
             * можно сделать из этого компонента.
             */
            $can_make =
                floor(
                    $remaining / $needed
                );

            /*
             * Самый ограничивающий компонент
             * определяет общий тираж.
             */
            if (
                $can_make < $max_possible
            ) {
                $max_possible =
                    $can_make;
            }

            /*
             * Если уже 0 —
             * дальше можно не считать.
             */
            if ($max_possible <= 0) {
                return 0;
            }
        }

        /*
         * Ограничиваем рассчитанный тираж
         * реальной возможностью производства.
         */
        $result =
            floor(
                min(
                    $prodqty,
                    $max_possible
                )
            );

        return max(
            0,
            $result
        );
    }

    /**
     * Строка DataView.
     */
    public function listOnRow($row)
    {
        $item =
            $row->getDataItem();

        $row->add(
            new CheckBox(
                'selected',
                new Bind(
                    $item,
                    'selected'
                )
            )
        );

        $row->add(
            new Label(
                'itemname',
                $item->itemname
            )
        );

        $row->add(
            new Label(
                'sales',
                H::fqty($item->sales)
            )
        );

        $row->add(
            new Label(
                'recom',
                H::fqty($item->recom)
            )
        );

        $row->add(
            new Label(
                'minqty',
                H::fqty($item->minqty)
            )
        );

        $row->add(
            new Label(
                'qty',
                H::fqty($item->qty)
            )
        );

        $row->add(
            new TextInput(
                'prodqty',
                new Bind(
                    $item,
                    'prodqty'
                )
            )
        );

        $row->add(
            new ClickLink('plus')
        );

        $row->add(
            new ClickLink('minus')
        );
    }

    /**
     * Создание прихода.
     */
    public function onExport($sender)
    {
        $selected = [];

        foreach ($this->_list as $row) {

            if (
                $row->selected
                && (float)$row->prodqty > 0
            ) {
                $selected[] =
                    $row;
            }
        }

        if (count($selected) == 0) {

            $this->setError(
                'Нет выбранных позиций'
            );

            return;
        }

        $doc =
            Document::create(
                'ProdReceipt'
            );

        $doc->document_number =
            $doc->nextNumber();

        $doc->document_date =
            time();

        $doc->notes =
            'Сформировано из Min2Prod';

        $doc->headerdata['store'] =
            $this->_store_id;

        $doc->headerdata['storename'] =
            $this->searchform
                ->store
                ->getValueName();

        $itemlist = [];

        foreach ($selected as $row) {

            $item =
                Item::load(
                    $row->item_id
                );

            if ($item == null) {
                continue;
            }

            $item->quantity =
                (float)$row->prodqty;

            if (
                method_exists(
                    $item,
                    'getProdprice'
                )
            ) {

                $item->price =
                    $item->getProdprice(
                        $this->_store_id
                    );

            } else {

                $item->price =
                    0;
            }

            $itemlist[] =
                $item;
        }

        if (count($itemlist) == 0) {

            $this->setError(
                'Нет товаров для оприходования'
            );

            return;
        }

        $doc->packDetails(
            'detaildata',
            $itemlist
        );

        $doc->amount =
            0;

        $doc->save();

        $doc->updateStatus(
            Document::STATE_NEW
        );

        $this->setSuccess(
            'Создан документ '
            . $doc->document_number
        );

        App::Redirect(
            '\\App\\Pages\\Doc\\ProdReceipt',
            $doc->document_id
        );
    }
}