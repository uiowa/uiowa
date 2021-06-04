var tag = document.createElement('script');

tag.src = "https://developers.panopto.com/scripts/embedapi.min.js";

let player_id="panopto_player-1";
let player_div=document.getElementById(player_id);

var firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

var embedApi;
function onPanoptoEmbedApiReady()
{
  embedApi = new EmbedApi(player_id, {
    width: player_div.dataset.width,
    height: player_div.dataset.height,
    //This is the URL of your Panopto site
    serverName: "uicapture.hosted.panopto.com",
    sessionId: player_div.dataset.link,
    videoParams: {
      "interactivity": "none",
      "showtitle": "false"
    }
  });
}
