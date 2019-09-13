<?php
 
class Row {
    private $positionId;
    private $orderDateFrom;
    private $deliveryDateFrom;
    private $price;
 
    public function __construct($positionId, $orderDateFrom, $deliveryDateFrom, $price) {
        $this->positionId = $positionId;
        $this->orderDateFrom = new DateTime($orderDateFrom);
        $this->deliveryDateFrom = new DateTime($deliveryDateFrom);
        $this->price = $price;
    }
 
    /**
     * @return DateTime|string
     */
    public function getOrderDateFrom(string $format = null) {
        if ($format) {
            return $this->orderDateFrom->format($format);
        }
        return $this->orderDateFrom;
    }
 
    /**
     * @return DateTime|string
     */
    public function getDeliveryDateFrom(string $format = null) {
        if ($format) {
            return $this->deliveryDateFrom->format($format);
        }
        return $this->deliveryDateFrom;
    }
 
    /**
     * @return mixed
     */
    public function getPrice() {
        return $this->price;
    }
 
    /**
     * @return mixed
     */
    public function getPositionId() {
        return $this->positionId;
    }
}
 
class RowService {
 
    public function getWhoHasMoreDateOrderAndDelivery(Row $currentRow, array $rows): ?Row {
        foreach ($rows as $row) {
            if ($row->getDeliveryDateFrom() > $currentRow->getDeliveryDateFrom()
                && $row->getOrderDateFrom() > $currentRow->getOrderDateFrom()) {
                return $row;
            }
        }
        return null;
    }
 
    public function getOneWhoHasMoreDeliveryDate(Row $currentRow, array $rows): ?Row {
        $results = array_filter($rows, function (Row $row) use ($currentRow) {
            return $row->getDeliveryDateFrom() > $currentRow->getDeliveryDateFrom();
        });
        uasort($results, function (Row $row, Row $row2) {
            return $row->getDeliveryDateFrom() <=> $row2->getDeliveryDateFrom();
        });
        return $results[0];
    }
}
 
class Transform {
    /** @var Row[] */
    private $rows;
    /**
     * @var RowService
     */
    private $rowService;
 
    public function __construct(RowService $rowService) {
        $this->rowService = $rowService;
    }
 
    /**
     * @param Row[]
     *
     * @return array
     */
    public function build(array $rows) {
        $this->rows = $rows;
        $result = [];
        foreach ($this->rows as $row) {
            if ($moreDeliveryAndOrderDateRow = $this->rowService->getWhoHasMoreDateOrderAndDelivery($row, $this->rows)) {
                $result[] = [
                    'order_date_from' => $row->getOrderDateFrom('Y-m-d'),
                    'order_date_to' => (clone $moreDeliveryAndOrderDateRow->getOrderDateFrom())
                        ->modify('- 1 day')
                        ->format('Y-m-d'),
                    'delivery_date_from' => $moreDeliveryAndOrderDateRow->getDeliveryDateFrom('Y-m-d'),
                    'delivery_date_to' => '',
                    'price' => $row->getPrice(),
                ];
                $result[] = [
                    'order_date_from' => $row->getOrderDateFrom('Y-m-d'),
                    'order_date_to' => '',
                    'delivery_date_from' => $row->getDeliveryDateFrom('Y-m-d'),
                    'delivery_date_to' => (clone $moreDeliveryAndOrderDateRow->getDeliveryDateFrom())
                        ->modify('- 1 day')
                        ->format('Y-m-d'),
                    'price' => $row->getPrice(),
                ];
                continue;
            }
 
            if (!$moreDeliveryDateRow = $this->rowService->getOneWhoHasMoreDeliveryDate($row, $this->rows)) {
                $result[] = [
                    'order_date_from' => $row->getOrderDateFrom('Y-m-d'),
                    'order_date_to' => '',
                    'delivery_date_from' => $row->getDeliveryDateFrom('Y-m-d'),
                    'delivery_date_to' => '',
                    'price' => $row->getPrice(),
                ];
                continue;
            }
 
            $result[] = [
                'order_date_from' => $row->getOrderDateFrom('Y-m-d'),
                'order_date_to' => '',
                'delivery_date_from' => $row->getDeliveryDateFrom('Y-m-d'),
                'delivery_date_to' => (clone $moreDeliveryDateRow->getDeliveryDateFrom())->modify('- 1 day')
                    ->format('Y-m-d'),
                'price' => $row->getPrice(),
            ];
        }
 
        return $result;
    }
}
