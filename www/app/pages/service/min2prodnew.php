<?php

namespace App\Pages\Service;

use App\Application as App;
use App\Entity\Doc\Document;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use App\Entity\Item;
use App\Entity\Store;

class Min2prod extends \App\Pages\Base
{
    public $_itemlist = array();

    public function __construct()
    {
        parent::__construct();

        if (false == \App\ACL::checkShowSer('Min2prod')) {
            return;
        }

        $this->add(new Form('searchform'))->onSubmit($this, 'updatelist');
        $this->searchform->add(new DropDownChoice('store', Store::getList(), H::getDefStore()));
        $this->searchform->add(new TextInput('search'));
        $this->searchform->add(new DropDownChoice(
            'cat',
            \App\Entity\Category::findArray(
                'cat_name',
                'cat_id in (select cat_id from items)',
                'cat_name'
            )
        ));

        $this->add(new Form('exportform'))->onSubmit($this, 'onExport');
        $this->exportform->add(
            new DataView(
                'itemlist',
                new ArrayDataSource($this, '_itemlist'),
                $this,
                'onRow'
            )
        );

        $this->updatelist(null);
    }

    public function onRow($row)
    {
        $item = $row->getDataItem();

        $row->add(new Label('itemname', $item->itemname));
        $row->add(new Label('qw', round(H::fqty($item->qw))));
        $row->add(new Label('minqty', round(H::fqty($item->minqty))));
        $row->add(new Label('qty', round(H::fqty($item->qty))));
        $row->add(new Label('prodqty', round(H::fqty($item->prodqty))));

        $row->add(
            new \Zippy\Html\Form\CheckBox(
                'sel',
                new \Zippy\Binding\PropertyBinding($item, 'sel')
            )
        );

        $row->add(
            new \Zippy\Html\Link\SubmitLink('delete')
        )->onClick($this, 'deleteOnClick');

        $row->add(
            new \Zippy\Html\Link\SubmitLink('plus')
        )->onClick($this, 'plusOnClick');

        $row->add(
            new \Zippy\Html\Link\SubmitLink('minus')
        )->onClick($this, 'minusOnClick');
    }

    public function plusOnClick($sender)
    {
        $item = $sender->owner->getDataItem();
        $item->prodqty++;
        $this->exportform->itemlist->Reload();
    }

    public function minusOnClick($sender)
    {
        $item = $sender->owner->getDataItem();

        if ($item->prodqty > 0) {
            $item->prodqty--;
        }

        $this->exportform->itemlist->Reload();
    }

    public function deleteOnClick($sender)
    {
        $id = $sender->owner->getDataItem()->item_id;
        $tmp = array();

        foreach ($this->_itemlist as $it) {
            if ($it->item_id == $id) {
                continue;
            }

            $tmp[] = $it;
        }

        $this->_itemlist = $tmp;
        $this->exportform->itemlist->Reload();
    }

    /**
     * Получает recom из items_view.detail -> cflist.
     */
    private function getRecom($itemId, $detail = null)
    {
        $conn = \ZDB\DB::getConnect();

        if ($detail === null) {
            $detail = $conn->GetOne(
                "select detail from items_view where item_id = " . intval($itemId)
            );
        }

        if (empty($detail)) {
            return 0;
        }

        if (!preg_match('/<cflist>(.*?)<\/cflist>/s', $detail, $matches)) {
            return 0;
        }

        $cflist = trim($matches[1]);

        if ($cflist === '') {
            return 0;
        }

        $data = @unserialize($cflist);

        if (!is_array($data) || !isset($data['recom'])) {
            return 0;
        }

        $recom = (float)$data['recom'];

        return $recom > 0 ? $recom : 0;
    }

    public function updatelist($sender)
    {
        $conn = \ZDB\DB::getConnect();

        $from = $conn->DBDate(strtotime("-1 week"));
        $to = $conn->DBDate(time());

        $cstr = \App\Acl::getStoreBranchConstraint();

        if (strlen($cstr) > 0) {
            $cstr = "store_id in ({$cstr}) and ";
        }

        $store = $this->searchform->store->getValue();
        $cat = $this->searchform->cat->getValue();
        $text = trim($this->searchform->search->getText());

        $name = "";

        if (strlen($text) > 0) {
            $text = $conn->qstr('%' . $text . '%');
            $name = " and (i.itemname like {$text} or cat_name like {$text})";
        }

        $cats = "";

        if ($cat > 0) {
            $cats = " and i.cat_id = " . intval($cat);
        }

        /*
         * Сначала получаем обычный список товаров.
         * minqty из базы здесь используется только как запасной вариант,
         * если у товара нет recom.
         */
        $sql = "select
                    (i.minqty-t.qty) as prodqty,
                    t.qty,
                    i.item_id,
                    i.minqty,
                    i.itemname,
                    i.item_code,
                    i.detail
                from (
                    select
                        store_id,
                        item_id,
                        coalesce(sum(qty), 0) as qty
                    from store_stock
                    where {$cstr} store_id = {$store}
                    group by item_id
                ) t
                join items_view i on t.item_id = i.item_id
                where i.disabled <> 1
                    and i.item_type in (4, 5)
                    {$name}
                    {$cats}
                order by i.itemname";

        $rs = $conn->Execute($sql);

        $this->_itemlist = array();

        foreach ($rs as $row) {

            /*
             * Получаем recom из detail/cflist.
             * Если recom есть — он полностью заменяет minqty.
             * Если recom нет — используется старый minqty.
             */
            $recom = $this->getRecom($row['item_id'], $row['detail']);

            $effectiveMinQty = $recom > 0 ? $recom : (float)$row['minqty'];

            $row['minqty'] = $effectiveMinQty;

            /*
             * Товар должен попадать в список только если
             * фактическое количество меньше эффективного minqty.
             */
            if ((float)$row['qty'] >= $effectiveMinQty || $effectiveMinQty <= 0) {
                continue;
            }

            /*
             * Начальное количество производства:
             * recom/minqty - фактический остаток.
             */
            $row['prodqty'] = $effectiveMinQty - (float)$row['qty'];

            /*
             * Продажи за последнюю неделю.
             */
            $qw = $conn->GetOne(
                "select coalesce(sum(0-quantity), 0)
                 from entrylist_view
                 where document_id in (
                     select document_id
                     from documents_view
                     where date(document_date) >= {$from}
                       and date(document_date) <= {$to}
                       and meta_name in ('POSCheck', 'TTN', 'GoodsIssue')
                 )
                 and quantity < 0
                 and item_id = " . intval($row['item_id'])
            );

            $row['qw'] = $qw;

            /*
             * Коррекция количества для производства в зависимости от продаж.
             */
            if ($qw == 0) {
                $row['prodqty'] += 0;
            } elseif ($qw >= 1 && $qw <= 3) {
                $row['prodqty'] += 1;
            } elseif ($qw >= 4 && $qw <= 9) {
                $row['prodqty'] += 2;
            } elseif ($qw >= 10) {
                $row['prodqty'] += 5;
            }

            /*
             * Расчет количества с учетом комплектующих.
             */
            $parts = \App\Entity\ItemSet::find(
                "pitem_id=" . intval($row['item_id'])
            );

            $remaining_parts = array();

            foreach ($parts as $part) {
                $pi = \App\Entity\Item::load($part->item_id);

                if ($pi == null) {
                    continue;
                }

                $remaining_parts[$part->item_id] = $pi->getQuantity(32);
            }

            $max_possible = 1000000;

            foreach ($parts as $part) {
                $needed = (float)$part->qty;

                if ($needed <= 0) {
                    continue;
                }

                if (
                    !isset($remaining_parts[$part->item_id]) ||
                    $remaining_parts[$part->item_id] <= 0
                ) {
                    $max_possible = 0;
                    break;
                }

                $can_make = $remaining_parts[$part->item_id] / $needed;

                if ($can_make < $max_possible) {
                    $max_possible = $can_make;
                }
            }

            /*
             * Ограничиваем производство наличием комплектующих.
             */
            if ($max_possible <= 0) {
                $row['prodqty'] = 0;
            } else {
                $row['prodqty'] = floor(
                    min($max_possible, $row['prodqty'])
                );
            }

            $row['prodqty'] = max(0, $row['prodqty']);

            unset($row['detail']);

            $this->_itemlist[] = new \App\DataItem($row);
        }

        $this->exportform->itemlist->Reload();
    }

    public function onExport($sender)
    {
        $doc = Document::create('ProdReceipt');

        $doc->user_id = System::getUser()->user_id;
        $doc->document_number = $doc->nextNumber();
        $doc->headerdata['store'] = $this->searchform->store->getValue();
        $doc->headerdata['parea'] = 4;

        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();

        try {
            $items = array();

            foreach ($this->exportform->itemlist->getDataRows() as $row) {
                $it = $row->getDataItem();

                if ($it->sel == true && $it->prodqty > 0) {
                    $item = Item::load($it->item_id);

                    $item->quantity = $it->prodqty;
                    $item->price = $item->getProdprice();

                    if ($item->quantity > 0) {
                        $items[$item->item_id] = $item;
                        $doc->amount += $item->quantity * $item->price;
                    }
                }
            }

            if (count($items) == 0) {
                $this->setInfo("Пустой список ТМЦ");
                $conn->RollbackTrans();
                return;
            }

            $doc->packDetails('detaildata', $items);
            $doc->save();

            $doc->updateStatus(Document::STATE_NEW);
            $doc->updateStatus(Document::STATE_EXECUTED);

            $conn->CommitTrans();

            App::Redirect(
                "\\App\\Pages\\Register\\DocList",
                $doc->document_id
            );

        } catch (\Throwable $ee) {
            $conn->RollbackTrans();

            $this->setError($ee->getMessage());

            global $logger;

            $logger->error(
                $ee->getMessage() . " Документ " . $doc->meta_desc
            );
        }
    }
}