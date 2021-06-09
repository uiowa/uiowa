let tag = document.createElement('script');

tag.src = "https://developers.panopto.com/scripts/embedapi.min.js";


// Get all `.panopto-player` elements.
let players = document.getElementsByClassName("panopto-player");

let firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);


function onPanoptoEmbedApiReady()
{
  for (player of players) {
    let player_id = player.id;

    new EmbedApi(player_id, {
      width: player.dataset.width,
      height: player.dataset.height,
      //This is the URL of your Panopto site
      serverName: "uicapture.hosted.panopto.com",
      sessionId: player.dataset.link,
      videoParams: {
        "interactivity": "none",
        "showtitle": "false"
      }
    });
  }
}
