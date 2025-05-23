/**
 * @file
 * JavaScript for the date and time block.
 */

(function (Drupal, once) {
  Drupal.behaviors.signageDateTime = {
    attach: function (context) {
      // Use the `once` function to ensure behavior is applied only once.
      once('dateTime', '.date-time', context).forEach(function (element) {
        // Call `updateDateTime` immediately and set an interval for updates.
        updateDateTime(element);
        setInterval(() => updateDateTime(element), 10000);
      });
    },
  };

  // Function to update date and time.
  function updateDateTime(element) {
    const weekdayArray = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthArray = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    const currentTime = new Date();
    const weekday = weekdayArray[currentTime.getDay()];
    const day = currentTime.getDate();
    const month = monthArray[currentTime.getMonth()];
    let hours = currentTime.getHours();
    const minutes = currentTime.getMinutes();
    const ampm = hours < 12 ? 'AM' : 'PM';

    hours = hours > 12 ? hours - 12 : hours;
    hours = hours === 0 ? 12 : hours;

    const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;

    const dateStr = weekday + ', ' + month + ' ' + day;
    const timeStr = hours + ':' + formattedMinutes + ' ' + ampm;

    // Update the content in the date and time elements.
    element.querySelector('#datespan').innerText = dateStr;
    element.querySelector('#timespan').innerText = timeStr;
  }
})(Drupal, once);
