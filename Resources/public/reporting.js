/**
 * Draws the charts of the Kimai summary report. The page provides the chart data as JSON in
 * a script element, and Kimai provides Chart.js as the global `Chart`.
 */
( function ()
{
  'use strict';

  /**
   * ID of the script element that holds the chart data.
   *
   * @type {string}
   */
  const DATA_ELEMENT_ID = 'summary-chart-data';

  /**
   * ID of the canvas for the time-per-period bar chart.
   *
   * @type {string}
   */
  const BAR_CHART_ID = 'summary-bar-chart';

  /**
   * ID of the canvas for the share-per-group doughnut chart.
   *
   * @type {string}
   */
  const DOUGHNUT_CHART_ID = 'summary-doughnut-chart';

  /**
   * Selector for the colour swatches in the breakdown table.
   *
   * @type {string}
   */
  const SWATCH_SELECTOR = '.summary-swatch[data-color]';

  /**
   * Event Kimai dispatches once its own scripts, including the chart defaults, are ready.
   *
   * @type {string}
   */
  const READY_EVENT = 'kimai.initialized';

  /**
   * ID of the script element that holds the customer and project detail page addresses.
   *
   * @type {string}
   */
  const LINKS_ELEMENT_ID = 'summary-detail-links';

  /**
   * Report table rows that get a detail link, with the filter parameter that holds their ID
   * in Kimai's own link on the row's total.
   *
   * @type {{selector: string, parameter: string, type: string}[]}
   */
  const LINKED_ROWS = [
    { selector: '#reporting-content .dataTable tr.summary', parameter: 'customers[]', type: 'customers' },
    { selector: '#reporting-content .dataTable tr.project', parameter: 'projects[]', type: 'projects' },
  ];

  /**
   * Selector of the buttons that switch between the weekly, monthly and yearly report.
   *
   * @type {string}
   */
  const VIEW_SWITCH_SELECTOR = '[data-summary-view-switch]';

  /**
   * Selector of the period picker in Kimai's report filters, which the buttons follow.
   *
   * @type {string}
   */
  const PERIOD_PICKER_SELECTOR = '#report-form .btn-list > .btn-group';

  /**
   * Attribute naming the element that charts added to another report should move into.
   *
   * @type {string}
   */
  const MOVE_ATTRIBUTE = 'data-summary-move-to';

  /**
   * Number of seconds in an hour.
   *
   * @type {number}
   */
  const SECONDS_PER_HOUR = 3600;

  /**
   * Width, in pixels, of the gap in the surface colour between stacked segments and slices.
   *
   * @type {number}
   */
  const SEGMENT_GAP = 2;

  /**
   * Shortest bar segment, in pixels, so short records stay visible next to long ones.
   *
   * @type {number}
   */
  const MIN_BAR_LENGTH = 6;

  /**
   * Smallest share of the doughnut a group is drawn with, so short records stay visible.
   * Tooltips always show the real duration and percentage.
   *
   * @type {number}
   */
  const MIN_DOUGHNUT_SHARE = 0.03;

  /**
   * Formats a number of seconds as hours and minutes, for example 1:05.
   *
   * @param {number} seconds The duration in seconds.
   * @returns {string}
   */
  function formatDuration( seconds )
  {
    const minutes = Math.round( seconds / 60 );
    const hours = Math.floor( minutes / 60 );
    const rest = minutes % 60;

    return hours + ':' + String( rest ).padStart( 2, '0' );
  }

  /**
   * Reads the chart data that the page embeds as JSON.
   *
   * @returns {?{buckets: string[], series: {label: string, color: string, colorDark: string, data: number[]}[], gridColor: string, labels: {total: string}}}
   */
  function readChartData()
  {
    const element = document.getElementById( DATA_ELEMENT_ID );
    if ( element === null )
    {
      return null;
    }

    try
    {
      return JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Summary report: invalid chart data', error );
      return null;
    }
  }

  /**
   * Returns whether Kimai shows its dark theme.
   *
   * @returns {boolean}
   */
  function isDarkTheme()
  {
    return document.documentElement.dataset.bsTheme === 'dark';
  }

  /**
   * Returns the colour for the current theme; groups without a dark step use their own colour.
   *
   * @param {string} color The colour for the light theme.
   * @param {string|undefined} colorDark The colour for the dark theme.
   * @returns {string}
   */
  function themeColor( color, colorDark )
  {
    return isDarkTheme() && colorDark ? colorDark : color;
  }

  /**
   * Returns the background colour of the card a chart sits on, for the gaps between segments.
   *
   * @param {HTMLElement} canvas The chart canvas.
   * @returns {string}
   */
  function surfaceColor( canvas )
  {
    const card = canvas.closest( '.card' ) ?? document.body;

    return window.getComputedStyle( card ).backgroundColor;
  }

  /**
   * Returns the total number of seconds in one series.
   *
   * @param {number[]} values Seconds per bucket.
   * @returns {number}
   */
  function sum( values )
  {
    return values.reduce( ( total, value ) => total + value, 0 );
  }

  /**
   * Gives every colour swatch in the breakdown table its group colour.
   *
   * @returns {void}
   */
  function paintSwatches()
  {
    document.querySelectorAll( SWATCH_SELECTOR ).forEach( ( swatch ) =>
    {
      swatch.style.backgroundColor = themeColor( swatch.dataset.color, swatch.dataset.colorDark );
    } );
  }

  /**
   * Draws the stacked bar chart with the time per day or month.
   *
   * @param {{buckets: string[], series: {label: string, color: string, colorDark: string, data: number[]}[], gridColor: string, labels: {total: string}}} data The chart data.
   * @returns {void}
   */
  function renderBarChart( data )
  {
    const canvas = document.getElementById( BAR_CHART_ID );
    if ( canvas === null )
    {
      return;
    }

    const totals = data.buckets.map( ( label, index ) => sum( data.series.map( ( series ) => series.data[ index ] ) ) );

    new Chart( canvas, {
      type: 'bar',
      data: {
        labels: data.buckets,
        datasets: data.series.map( ( series ) => ( {
          label: series.label,
          backgroundColor: themeColor( series.color, series.colorDark ),
          borderColor: surfaceColor( canvas ),
          borderWidth: { top: SEGMENT_GAP },
          // Empty days get no value at all, so the minimum length only applies to real records.
          data: series.data.map( ( seconds ) => seconds > 0 ? seconds / SECONDS_PER_HOUR : null ),
          seconds: series.data,
          minBarLength: MIN_BAR_LENGTH,
        } ) ),
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
          x: {
            stacked: true,
            grid: { display: false },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            grid: { color: data.gridColor },
            ticks: { callback: ( value ) => formatDuration( value * SECONDS_PER_HOUR ) },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            filter: ( item ) => item.dataset.seconds[ item.dataIndex ] > 0,
            callbacks: {
              label: ( item ) => ' ' + item.dataset.label + ': ' + formatDuration( item.dataset.seconds[ item.dataIndex ] ),
              footer: ( items ) => data.labels.total + ': ' + formatDuration( totals[ items[ 0 ].dataIndex ] ),
            },
          },
        },
      },
    } );
  }

  /**
   * Draws the doughnut chart with each group's share of the total time.
   *
   * @param {{series: {label: string, color: string, colorDark: string, data: number[]}[]}} data The chart data.
   * @returns {void}
   */
  function renderDoughnutChart( data )
  {
    const canvas = document.getElementById( DOUGHNUT_CHART_ID );
    if ( canvas === null )
    {
      return;
    }

    const durations = data.series.map( ( series ) => sum( series.data ) );
    const total = sum( durations );
    const drawn = durations.map( ( duration ) => duration > 0 ? Math.max( duration, total * MIN_DOUGHNUT_SHARE ) : 0 );

    new Chart( canvas, {
      type: 'doughnut',
      data: {
        labels: data.series.map( ( series ) => series.label ),
        datasets: [ {
          backgroundColor: data.series.map( ( series ) => themeColor( series.color, series.colorDark ) ),
          borderColor: surfaceColor( canvas ),
          borderWidth: SEGMENT_GAP,
          data: drawn,
        } ],
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        cutout: '60%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ( item ) =>
              {
                const duration = durations[ item.dataIndex ];
                const percent = total > 0 ? ( duration / total * 100 ).toFixed( 1 ) : '0.0';

                return ' ' + formatDuration( duration ) + ' (' + percent + '%)';
              },
            },
          },
        },
      },
    } );
  }

  /**
   * Moves charts that were added to another report to the top of that report's content,
   * below its filters.
   *
   * @returns {void}
   */
  function placeCharts()
  {
    document.querySelectorAll( '[' + MOVE_ATTRIBUTE + ']' ).forEach( ( charts ) =>
    {
      const target = document.getElementById( charts.getAttribute( MOVE_ATTRIBUTE ) );
      if ( target !== null )
      {
        target.prepend( charts );
      }
    } );
  }

  /**
   * Turns the customer and project names in the report table into links to their detail
   * pages. The row's ID is read from Kimai's own link on the row's total.
   *
   * @returns {void}
   */
  function linkReportRows()
  {
    const element = document.getElementById( LINKS_ELEMENT_ID );
    if ( element === null )
    {
      return;
    }

    let links;
    try
    {
      links = JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Summary report: invalid detail links', error );
      return;
    }

    LINKED_ROWS.forEach( ( { selector, parameter, type } ) =>
    {
      document.querySelectorAll( selector ).forEach( ( row ) =>
      {
        const filterLink = row.querySelector( 'a[href]' );
        const nameCell = row.cells[ 0 ];
        if ( filterLink === null || nameCell === undefined )
        {
          return;
        }

        const id = new URL( filterLink.href, window.location.href ).searchParams.get( parameter );
        const url = id === null ? undefined : links[ type ]?.[ id ];
        if ( url === undefined )
        {
          return;
        }

        const link = document.createElement( 'a' );
        link.href = url;
        link.className = 'summary-detail-link';
        link.append( ...nameCell.childNodes );
        nameCell.append( link );
      } );
    } );
  }

  /**
   * Puts the buttons for the other two user reports right of the report's period picker.
   *
   * @returns {void}
   */
  function placeViewSwitch()
  {
    const viewSwitch = document.querySelector( VIEW_SWITCH_SELECTOR );
    const periodPicker = document.querySelector( PERIOD_PICKER_SELECTOR );
    if ( viewSwitch === null || periodPicker === null )
    {
      return;
    }

    periodPicker.after( viewSwitch );
    viewSwitch.hidden = false;
  }

  /**
   * Draws everything once Kimai is ready.
   *
   * @returns {void}
   */
  function init()
  {
    placeCharts();
    placeViewSwitch();
    linkReportRows();
    paintSwatches();

    const data = readChartData();
    if ( data === null || typeof Chart === 'undefined' )
    {
      return;
    }

    renderBarChart( data );
    renderDoughnutChart( data );
  }

  document.addEventListener( READY_EVENT, init );
} )();
