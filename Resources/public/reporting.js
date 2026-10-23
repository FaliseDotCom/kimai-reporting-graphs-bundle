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
   * Filter parameter that holds a project ID in Kimai's links to the record list.
   *
   * @type {string}
   */
  const PROJECT_PARAMETER = 'projects[]';

  /**
   * Report table rows that get a detail link, with the filter parameter that holds their ID
   * in Kimai's own link on the row's total.
   *
   * @type {{selector: string, parameter: string, type: string}[]}
   */
  const LINKED_ROWS = [
    { selector: '#reporting-content .dataTable tr.summary', parameter: 'customers[]', type: 'customers' },
    { selector: '#reporting-content .dataTable tr.project', parameter: PROJECT_PARAMETER, type: 'projects' },
  ];

  /**
   * Selector of the user report table, Kimai's table of customers, projects and activities.
   *
   * @type {string}
   */
  const REPORT_TABLE_SELECTOR = '#reporting-content .dataTable';

  /**
   * Classes of the customer, project and activity rows in the user report table.
   *
   * @type {{customer: string, project: string, activity: string}}
   */
  const ROW_CLASSES = { customer: 'summary', project: 'project', activity: 'activity' };

  /**
   * Index of the cell in a report table row that holds Kimai's filter link on the row's total.
   *
   * @type {number}
   */
  const TOTAL_CELL = 1;

  /**
   * Selector of the legend items under the doughnut chart, which list the keys of the groups
   * they cover, such as "p12" for project 12.
   *
   * @type {string}
   */
  const LEGEND_ITEM_SELECTOR = '.summary-legend-item[data-summary-keys]';

  /**
   * Selector of the duration in a legend item.
   *
   * @type {string}
   */
  const LEGEND_DURATION_SELECTOR = '.summary-group-duration';

  /**
   * Prefix of a project's group key.
   *
   * @type {string}
   */
  const PROJECT_KEY_PREFIX = 'p';

  /**
   * Selector of the hidden sort buttons, which are moved into the report table's header.
   *
   * @type {string}
   */
  const SORT_BUTTONS_SELECTOR = '[data-summary-sort-buttons]';

  /**
   * Sort directions, as used in the header's aria-sort attribute.
   *
   * @type {{ascending: string, descending: string, none: string}}
   */
  const SORT_DIRECTIONS = { ascending: 'ascending', descending: 'descending', none: 'none' };

  /**
   * The value of the data-summary-sort attribute of the button that sorts by name.
   *
   * @type {string}
   */
  const SORT_BY_NAME = 'name';

  /**
   * The value of the data-summary-sort attribute of the button that sorts by total.
   *
   * @type {string}
   */
  const SORT_BY_TOTAL = 'total';

  /**
   * Sortable columns, by the value of their button's data-summary-sort attribute: the index of
   * their header cell, and the direction of the first sort.
   *
   * @type {Object<string, {cell: number, first: string}>}
   */
  const SORT_COLUMNS = {
    [ SORT_BY_NAME ]: { cell: 0, first: SORT_DIRECTIONS.ascending },
    [ SORT_BY_TOTAL ]: { cell: TOTAL_CELL, first: SORT_DIRECTIONS.descending },
  };

  /**
   * Font Awesome icon of a sort button per direction.
   *
   * @type {Object<string, string>}
   */
  const SORT_ICONS = {
    [ SORT_DIRECTIONS.ascending ]: 'fa-sort-up',
    [ SORT_DIRECTIONS.descending ]: 'fa-sort-down',
    [ SORT_DIRECTIONS.none ]: 'fa-sort',
  };

  /**
   * Selector of the icon in a sort button.
   *
   * @type {string}
   */
  const SORT_ICON_SELECTOR = '.summary-sort-icon';

  /**
   * Browser storage key of the last chosen sort, so it stays when another report is opened.
   *
   * @type {string}
   */
  const SORT_STORAGE_KEY = 'reportingGraphs.userReportSort';

  /**
   * Selector of the buttons that switch between the weekly, monthly, quarterly and yearly report.
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
   * ID of the script element that holds the period letter per report address, on Kimai's
   * reporting overview.
   *
   * @type {string}
   */
  const PERIOD_BADGES_ELEMENT_ID = 'report-period-badges';

  /**
   * Selector of a report card on Kimai's reporting overview.
   *
   * @type {string}
   */
  const REPORT_CARD_SELECTOR = 'a.card-link';

  /**
   * Selectors of the icon and the name inside a report card.
   *
   * @type {{icon: string, label: string}}
   */
  const REPORT_CARD_PARTS = { icon: '.avatar', label: '.col' };

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
   * Returns Kimai's link on the total of a report table row, which opens the row's records.
   *
   * @param {HTMLTableRowElement} row A customer, project or activity row.
   * @returns {?HTMLAnchorElement}
   */
  function getFilterLink( row )
  {
    return row.cells[ TOTAL_CELL ]?.querySelector( 'a[href]' ) ?? null;
  }

  /**
   * Links each duration in the doughnut chart's legend to the records it counts, the same way
   * Kimai links the project totals in the report table. The "other" item opens the records of
   * all the projects it combines.
   *
   * @returns {void}
   */
  function linkLegend()
  {
    const projectLinks = new Map();
    document.querySelectorAll( REPORT_TABLE_SELECTOR + ' tr.' + ROW_CLASSES.project ).forEach( ( row ) =>
    {
      const filterLink = getFilterLink( row );
      const id = filterLink === null ? null : new URL( filterLink.href, window.location.href ).searchParams.get( PROJECT_PARAMETER );
      if ( id !== null )
      {
        projectLinks.set( id, filterLink.href );
      }
    } );

    document.querySelectorAll( LEGEND_ITEM_SELECTOR ).forEach( ( item ) =>
    {
      const duration = item.querySelector( LEGEND_DURATION_SELECTOR );
      const ids = item.dataset.summaryKeys
        .split( ' ' )
        .filter( ( key ) => key.startsWith( PROJECT_KEY_PREFIX ) )
        .map( ( key ) => key.slice( PROJECT_KEY_PREFIX.length ) );
      if ( duration === null || ids.length === 0 || !ids.every( ( id ) => projectLinks.has( id ) ) )
      {
        return;
      }

      const url = new URL( projectLinks.get( ids[ 0 ] ), window.location.href );
      url.searchParams.delete( PROJECT_PARAMETER );
      ids.forEach( ( id ) => url.searchParams.append( PROJECT_PARAMETER, id ) );

      const link = document.createElement( 'a' );
      link.href = url.toString();
      link.append( ...duration.childNodes );
      duration.append( link );
    } );
  }

  /**
   * Reads a total from the report table as a number to sort by: a duration such as 20:45 in
   * seconds, and an amount or decimal duration such as 1.234,50 as its value.
   *
   * @param {string} text The cell's text.
   * @returns {number}
   */
  function parseTotal( text )
  {
    const value = text.trim();
    const time = value.match( /^(-?)(\d+):(\d{2})(?::(\d{2}))?$/ );
    if ( time !== null )
    {
      const seconds = Number( time[ 2 ] ) * SECONDS_PER_HOUR + Number( time[ 3 ] ) * 60 + Number( time[ 4 ] ?? 0 );

      return time[ 1 ] === '-' ? -seconds : seconds;
    }

    // The last separator followed by one or two digits marks the decimals; others group digits.
    const number = value.replace( /[^\d.,-]/g, '' );
    const decimals = number.match( /[.,](\d{1,2})$/ );
    const whole = ( decimals === null ? number : number.slice( 0, decimals.index ) ).replace( /[.,]/g, '' );
    const parsed = Number.parseFloat( whole + ( decimals === null ? '' : '.' + decimals[ 1 ] ) );

    return Number.isNaN( parsed ) ? 0 : parsed;
  }

  /**
   * Splits the report table's rows into customers, each with its projects, each with its
   * activities, keeping each row's name, total and original position.
   *
   * @param {HTMLTableSectionElement} body The table body.
   * @returns {{row: HTMLTableRowElement, index: number, name: string, total: number, children: Array}[]}
   */
  function readRowTree( body )
  {
    const customers = [];
    Array.from( body.rows ).forEach( ( row, index ) =>
    {
      const node = {
        row,
        index,
        name: row.cells[ 0 ]?.textContent.trim() ?? '',
        total: parseTotal( row.cells[ TOTAL_CELL ]?.textContent ?? '' ),
        children: [],
      };

      if ( row.classList.contains( ROW_CLASSES.customer ) )
      {
        customers.push( node );
      }
      else if ( row.classList.contains( ROW_CLASSES.project ) )
      {
        customers.at( -1 )?.children.push( node );
      }
      else if ( row.classList.contains( ROW_CLASSES.activity ) )
      {
        customers.at( -1 )?.children.at( -1 )?.children.push( node );
      }
    } );

    return customers;
  }

  /**
   * Sorts rows and, within each, their child rows by name or total. Equal rows keep their
   * original order.
   *
   * @param {{index: number, name: string, total: number, children: Array}[]} nodes The rows to sort.
   * @param {string} column One of the SORT_COLUMNS keys.
   * @param {string} direction SORT_DIRECTIONS.ascending or SORT_DIRECTIONS.descending.
   * @param {Intl.Collator} collator Compares names in the page's language.
   * @returns {void}
   */
  function sortRowTree( nodes, column, direction, collator )
  {
    const sign = direction === SORT_DIRECTIONS.descending ? -1 : 1;
    nodes.sort( ( first, second ) =>
    {
      const order = column === SORT_BY_NAME ? collator.compare( first.name, second.name ) : first.total - second.total;

      return sign * order || first.index - second.index;
    } );
    nodes.forEach( ( node ) => sortRowTree( node.children, column, direction, collator ) );
  }

  /**
   * Puts rows back into the table body in the order of the tree.
   *
   * @param {HTMLTableSectionElement} body The table body.
   * @param {{row: HTMLTableRowElement, children: Array}[]} nodes The sorted rows.
   * @returns {void}
   */
  function appendRowTree( body, nodes )
  {
    nodes.forEach( ( node ) =>
    {
      body.append( node.row );
      appendRowTree( body, node.children );
    } );
  }

  /**
   * Shows the sort on the header buttons: the active column's arrow and aria-sort.
   *
   * @param {HTMLButtonElement[]} buttons The sort buttons.
   * @param {string} column The sorted column.
   * @param {string} direction The sort direction.
   * @returns {void}
   */
  function showSort( buttons, column, direction )
  {
    buttons.forEach( ( button ) =>
    {
      const state = button.dataset.summarySort === column ? direction : SORT_DIRECTIONS.none;
      button.closest( 'th' )?.setAttribute( 'aria-sort', state );
      button.querySelector( SORT_ICON_SELECTOR )?.classList.remove( ...Object.values( SORT_ICONS ) );
      button.querySelector( SORT_ICON_SELECTOR )?.classList.add( SORT_ICONS[ state ] );
    } );
  }

  /**
   * Returns the sort chosen last, or null when there is none or browser storage is blocked.
   *
   * @returns {?{column: string, direction: string}}
   */
  function readSavedSort()
  {
    try
    {
      const saved = JSON.parse( window.localStorage.getItem( SORT_STORAGE_KEY ) ?? 'null' );
      const valid = saved !== null && saved.column in SORT_COLUMNS
        && [ SORT_DIRECTIONS.ascending, SORT_DIRECTIONS.descending ].includes( saved.direction );

      return valid ? saved : null;
    }
    catch ( error )
    {
      return null;
    }
  }

  /**
   * Remembers the chosen sort for the next report; does nothing when browser storage is blocked.
   *
   * @param {{column: string, direction: string}} sort The chosen sort.
   * @returns {void}
   */
  function saveSort( sort )
  {
    try
    {
      window.localStorage.setItem( SORT_STORAGE_KEY, JSON.stringify( sort ) );
    }
    catch ( error )
    {
      // The sort then only lasts until the page is left.
    }
  }

  /**
   * Makes the user report table sortable by customer name and by total, through buttons in
   * its header. Each click on the active column reverses the order; projects and activities
   * are sorted within their customer and project. Names start A to Z, totals largest first.
   *
   * @returns {void}
   */
  function enableTableSort()
  {
    const holder = document.querySelector( SORT_BUTTONS_SELECTOR );
    const table = document.querySelector( REPORT_TABLE_SELECTOR );
    const header = table?.tHead?.rows[ 0 ];
    const body = table?.tBodies[ 0 ];
    if ( holder === null || header === undefined || body === undefined || body.querySelector( 'tr.' + ROW_CLASSES.customer ) === null )
    {
      return;
    }

    const buttons = Array.from( holder.querySelectorAll( 'button[data-summary-sort]' ) );
    buttons.forEach( ( button ) =>
    {
      const cell = header.cells[ SORT_COLUMNS[ button.dataset.summarySort ]?.cell ];
      if ( cell === undefined )
      {
        return;
      }

      // The total keeps Kimai's own heading, which names what is summed.
      if ( button.dataset.summarySort === SORT_BY_TOTAL )
      {
        button.prepend( ...cell.childNodes );
      }
      cell.replaceChildren( button );
    } );

    const collator = new Intl.Collator( document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' } );
    const apply = ( sort ) =>
    {
      const tree = readRowTree( body );
      sortRowTree( tree, sort.column, sort.direction, collator );
      appendRowTree( body, tree );
      showSort( buttons, sort.column, sort.direction );
    };

    let current = readSavedSort();
    if ( current !== null )
    {
      apply( current );
    }

    buttons.forEach( ( button ) => button.addEventListener( 'click', () =>
    {
      const column = button.dataset.summarySort;
      const reversed = current?.direction === SORT_DIRECTIONS.ascending ? SORT_DIRECTIONS.descending : SORT_DIRECTIONS.ascending;
      current = { column, direction: current?.column === column ? reversed : SORT_COLUMNS[ column ].first };
      apply( current );
      saveSort( current );
    } ) );
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
        const filterLink = getFilterLink( row );
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
   * Gives the weekly, monthly, quarterly and yearly report cards on Kimai's reporting overview
   * a period letter on their icon, and makes the period, the first word of their name, bold.
   *
   * @returns {void}
   */
  function markReportPeriods()
  {
    const element = document.getElementById( PERIOD_BADGES_ELEMENT_ID );
    if ( element === null )
    {
      return;
    }

    let badges;
    try
    {
      badges = JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Reporting overview: invalid period letters', error );
      return;
    }

    document.querySelectorAll( REPORT_CARD_SELECTOR ).forEach( ( card ) =>
    {
      const href = card.getAttribute( 'href' );
      const icon = card.querySelector( REPORT_CARD_PARTS.icon );
      const label = card.querySelector( REPORT_CARD_PARTS.label );
      if ( !Object.hasOwn( badges, href ) || icon === null || label === null )
      {
        return;
      }

      const badge = document.createElement( 'span' );
      badge.className = 'report-period-badge';
      badge.setAttribute( 'aria-hidden', 'true' );
      badge.textContent = badges[ href ];
      icon.classList.add( 'report-period-icon' );
      icon.append( badge );
      boldFirstWord( label );
    } );
  }

  /**
   * Makes the first word of an element's text bold.
   *
   * @param {HTMLElement} element The element whose text is a plain name.
   * @returns {void}
   */
  function boldFirstWord( element )
  {
    const text = element.textContent.trim();
    const end = text.search( /\s/ );
    if ( end <= 0 )
    {
      return;
    }

    const word = document.createElement( 'strong' );
    word.textContent = text.slice( 0, end );
    element.replaceChildren( word, text.slice( end ) );
  }

  /**
   * Draws everything once Kimai is ready.
   *
   * @returns {void}
   */
  function init()
  {
    markReportPeriods();
    placeCharts();
    placeViewSwitch();
    linkReportRows();
    linkLegend();
    enableTableSort();
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
