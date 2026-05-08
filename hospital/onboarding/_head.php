<?php
$cpTitle = $cpTitle ?? 'Clinical Precision';
$cpStep  = $cpStep  ?? 1;
$cpLang  = $_SESSION['cp_lang'] ?? 'en';
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($cpLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#005ab4">
  <title><?= htmlspecialchars($cpTitle) ?> — Clinical Precision | Planeazzy</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/clinical.css">
  <link rel="icon" href="/assets/images/favicon.png" type="image/svg+xml">
</head>
<body>
