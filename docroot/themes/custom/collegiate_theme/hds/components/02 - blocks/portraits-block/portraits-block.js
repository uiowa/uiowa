var ytplayerList;

function onPlayerReady(e) {
  var video_data = e.target.getVideoData(),
  label = video_data.video_id + ':' + video_data.title;
  e.target.ulabel = label;
}

function onPlayerError(e) {}

function onPlayerStateChange(e) {
  var label = e.target.ulabel;
  if (e["data"] == YT.PlayerState.PLAYING) {
    //if one video is play then pause other
    pauseOthersYoutubes(e.target);
  }
  if (e["data"] == YT.PlayerState.PAUSED) {}
  if (e["data"] == YT.PlayerState.ENDED) {}
  //track number of buffering and quality of video
  if (e["data"] == YT.PlayerState.BUFFERING) {
    e.target.uBufferingCount ? ++e.target.uBufferingCount : e.target.uBufferingCount = 1;
    //if one video is play then pause other, this is needed because at start video is in buffered state and start playing without go to playing state
    if (YT.PlayerState.UNSTARTED == e.target.uLastPlayerState) {
      pauseOthersYoutubes(e.target);
    }
  }
  //last action keep stage in uLastPlayerState
  if (e.data != e.target.uLastPlayerState) {
    e.target.uLastPlayerState = e.data;
  }
}

function initYoutubePlayers() {
  ytplayerList = null; //reset
  ytplayerList = []; //create new array to hold youtube player
  for (var e = document.getElementsByTagName("iframe"), x = e.length; x--;) {
    if (/youtube.com\/embed/.test(e[x].src)) {
      ytplayerList.push(initYoutubePlayer(e[x]));
    }
  }

}

function pauseOthersYoutubes(currentPlayer) {
  if (!currentPlayer) return;
  for (var i = ytplayerList.length; i--;) {
    if (ytplayerList[i] && (ytplayerList[i] != currentPlayer)) {
      ytplayerList[i].pauseVideo();
    }
  }
}
//init a youtube iframe
function initYoutubePlayer(ytiframe) {
  var ytp = new YT.Player(ytiframe, {
    events: {
      onStateChange: onPlayerStateChange,
      onError: onPlayerError,
      onReady: onPlayerReady
    }
  });
  ytiframe.ytp = ytp;
  return ytp;
}

function onYouTubeIframeAPIReady() {
  initYoutubePlayers();
}
var tag = document.createElement('script');
//use https when loading script and youtube iframe src since if user is logging in youtube the youtube src will switch to https.
tag.src = "https://www.youtube.com/iframe_api";
var firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
