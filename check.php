<?php

const DATE_FORMAT = 'Y-m-d';
function getCheckDates($inputs)
{
    $dates = [];

    /** @var Row $input */
    foreach ($inputs as $input) {
        $dates[] = $input->getDeliveryDateFrom();
        $dates[] = $input->getOrderDateFrom();
        $dates[] = clone($input->getDeliveryDateFrom())->add(new DateInterval('P1D'));
        $dates[] = clone($input->getOrderDateFrom())->add(new DateInterval('P1D'));
        $dates[] = clone($input->getDeliveryDateFrom())->sub(new DateInterval('P1D'));
        $dates[] = clone($input->getOrderDateFrom())->sub(new DateInterval('P1D'));
    }

    asort($dates);

    $dates = array_map(function (DateTime $date) {
        return $date->format('Y-m-d');
    }, $dates);

    return array_map(function(string $date){
        return DateTime::createFromFormat(DATE_FORMAT, $date);
    }, array_unique($dates));
}

function getPriceFromInputs($inputs, DateTime $orderDateStart, DateTime $deliveryDateStart)
{
    $allowedInputs = array_filter($inputs, function (Row $input) use ($orderDateStart, $deliveryDateStart) {
        return $input->getOrderDateFrom() <= $orderDateStart && $input->getDeliveryDateFrom() <= $deliveryDateStart;
    });

    if (!$allowedInputs) {
        return null;
    }

    usort($allowedInputs, function (Row $a, Row $b) {

        $cmp = $a->getDeliveryDateFrom()->getTimestamp() - $b->getDeliveryDateFrom()->getTimestamp();

        if ($cmp === 0) {
            return $this->getOrderDateFrom()->getTimestamp() - $b->getOrderDateFrom()->getTimestamp();
        }

        return -$cmp;
    });

    return $allowedInputs[0]->getPrice();
}

function getPriceFromOutputs($outputs, DateTime $orderDateStart, DateTime $deliveryDateStart)
{
    $allowedOutputs = array_values(array_filter($outputs, function (array $output) use ($orderDateStart, $deliveryDateStart) {
        $orderDateFrom = DateTime::createFromFormat(DATE_FORMAT, $output['order_date_from']);
        $orderDateTo = $output['order_date_to'] ? DateTime::createFromFormat(DATE_FORMAT, $output['order_date_to']) : null;
        $deliveryDateFrom = DateTime::createFromFormat(DATE_FORMAT, $output['delivery_date_from']);
        $deliveryDateTo = $output['delivery_date_to'] ? DateTime::createFromFormat(DATE_FORMAT, $output['delivery_date_to']) : null;

        return  $orderDateFrom<= $orderDateStart
            && $deliveryDateFrom <= $deliveryDateStart
            && ($orderDateTo === null || $orderDateTo >= $orderDateStart)
            && ($deliveryDateTo === null || $deliveryDateTo >= $deliveryDateStart);
    }));

    if (count($allowedOutputs) == 0) {
        return null;
    }

    if (count($allowedOutputs) > 1) {
        var_dump($allowedOutputs);
        throw new \Exception("multiple items found for {$orderDateStart->format('Y-m-d')} {$deliveryDateStart->format('Y-m-d')}, для одной пары дат должен быть только один результат");
    }

    return $allowedOutputs[0]['price'];
}

function crossCheck($inputs, $outputs)
{
    $dates = getCheckDates($inputs);

    foreach ($dates as $orderDate) {

        foreach ($dates as $deliveryDate) {

            if ($deliveryDate >= $orderDate) {

                $inputsPrice = getPriceFromInputs($inputs, $orderDate, $deliveryDate);
                $outputsPrice = getPriceFromOutputs($outputs, $orderDate, $deliveryDate);

               if($inputsPrice != $outputsPrice) {
                   echo "Error price for orderDate {$orderDate->format('Y-m-d')} and deliveryDate {$deliveryDate->format('Y-m-d')} \r\n";
                   echo "Required {$inputsPrice} but find  {$outputsPrice} \r\n";
               } else {
                   echo "OK orderDate {$orderDate->format('Y-m-d')} and deliveryDate {$deliveryDate->format('Y-m-d')} \r\n";
               }
            }
        }
    }

    return true;
}

include('test.php');

$inputs = [
//    new Row(1, '2019-02-01', '2019-03-01', 100),
//    new Row(1, '2019-02-10', '2019-03-10', 200),
//    new Row(1, '2019-02-20', '2019-02-25', 130),
    new Row(1, '2018-08-01', '2018-08-01', 300),
    new Row(1, '2018-09-01', '2018-09-01', 140),
    new Row(1, '2018-09-01', '2019-06-10', 170),
    new Row(1, '2018-12-25', '2018-12-25', 315),
    new Row(1, '2019-05-01', '2019-05-01', 315),
    new Row(1, '2019-05-01', '2019-05-06', 170),
    new Row(1, '2019-05-01', '2019-06-02', 170),
    new Row(1, '2019-05-01', '2019-07-22', 140)
];

$outputs = (new Transform(
    new RowService()
))->build($inputs);


crossCheck($inputs, $outputs);