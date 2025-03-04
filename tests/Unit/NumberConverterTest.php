<?php

use App\Func\NumberConverter;

test('convert MB to MiB', function () {
    $result = NumberConverter::convert(1000, 'MB', 'MiB');
    expect($result)->toBeFloat()->toEqual(1024.0);
});

test('convert MiB to MB', function () {
    $result = NumberConverter::convert(1024, 'MiB', 'MB');
    expect($result)->toBeFloat()->toEqual(1000.0);
});

test('convert with unit included', function () {
    $result = NumberConverter::convert(1024, 'MiB', 'GB', true);
    expect($result)->toBeString()->toEqual('1 GB');
});

test('convert with custom precision', function () {
    $result = NumberConverter::convert(1024, 'MiB', 'GB', false, 3);
    expect($result)->toBeFloat()->toEqual(1.074);
});

test('convert to auto unit', function () {
    $result = NumberConverter::convert(1500000, 'B', 'auto', true);
    expect($result)->toBeString()->toEqual('2 MB');
});

test('convert to iauto unit', function () {
    $result = NumberConverter::convert(1500000, 'B', 'iauto', true);
    expect($result)->toBeString()->toEqual('1 MiB');
});

test('convert throws exception for invalid unit', function () {
    expect(fn() => NumberConverter::convert(100, 'InvalidUnit', 'MB'))
        ->toThrow(InvalidArgumentException::class, 'Invalid unit specified');
});

test('convert CPU core to percentage', function () {
    $result = NumberConverter::convertCpuCore(0.5, false);
    expect($result)->toBeFloat()->toEqual(50.0);
});

test('convert percentage to CPU core', function () {
    $result = NumberConverter::convertCpuCore(50, true);
    expect($result)->toBeFloat()->toEqual(0.5);
});

test('convert CPU core with custom precision', function () {
    $result = NumberConverter::convertCpuCore(33.333, true, 3);
    expect($result)->toBeFloat()->toEqual(0.333);
});
