<?php
$info = [
  // Available colors and color labels used in theme.
  'fields' => [
    '#type' => 'checkbox',
    '#title' => t('Color module'),
    '#description' => t('Use Color module'),
    '#default_value' => theme_get_setting('inline_logo')
  ],

  'fields' => [
    'primary' => t('Primary'),
    'light' => t('Light'),
    'lighter' => t('Lighter'),
    'dark' => t('Dark'),
    'text' => t('Text Color'),
    'background' => t('Background Color')
  ],
  // Pre-defined color schemes.
  'schemes' => [
    'default' => [
      'title' => t('Default'),
      'colors' => [
         'primary' => '#41449f',
         'light' => '#babad3',
         'lighter' => '#e9e9f2',
         'dark' => '#272727',
         'text' => '#333333',
         'background' => '#ffffff'
      ],
    ],
    'blossom' => [
      'title' => t('Apple Blossom'),
      'colors' => [
         'primary' => '#9F4143',
         'lighter' => '#F2E9E9',
         'light' => '#D3BABA',
         'dark' => '#592750',
         'text' => '#212121',
         'background' => '#ffffff'
      ],
    ],
    'marine' => [
      'title' => t('Marine'),
      'colors' => [
         'primary' => '#437A9E',
         'light' => '#A5B6C1',
         'lighter' => '#d0e5f1',
         'dark' => '#555B5E',
         'text' => '#333333',
         'background' => '#ffffff'
      ],
    ],
    'khaki' => [
      'title' => t('Khaki'),
      'colors' => [
         'primary' => '#9f6941',
         'light' => '#bfa997',
         'lighter' => '#ece4db',
         'dark' => '#272727',
         'text' => '#272727',
         'background' => '#ffffff'
      ],
    ],
  ],

  // CSS files (excluding @import) to rewrite with new color scheme.
  'css' => [
    'assets/css/color.css',
  ],

  // Files to copy.
  'copy' => [
    'logo.svg',
  ],

  // Preview files.
  'preview_library' => 'belgrade/color.preview',
  'preview_html' => 'color/preview.html',

  // Attachments.
  '#attached' => [
    'drupalSettings' => [
      'color' => [
        // Put the logo path into JavaScript for the live preview.
        'logo' => theme_get_setting('logo.url', 'belgrade'),
      ],
    ],
  ],
];
