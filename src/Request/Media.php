<?php

namespace InstagramAPI\Request;

use InstagramAPI\Request;
use InstagramAPI\Constants;
use InstagramAPI\Response;
use InstagramAPI\Signatures;
use InstagramAPI\Utils;

/**
 * Functions for interacting with media items from yourself and others.
 *
 * @see Usertag for functions that let you tag people in media.
 */
class Media extends RequestCollection
{
    /**
     * Get detailed media information.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaInfoResponse
     */
    public function getInfo(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/info/")
            ->getResponse(new Response\MediaInfoResponse());
    }

    /**
     * Get detailed media information (with web API)
     *
     * @param string $mediaCode The media ID in Instagram's internal format (ie "CAk7sDZlw0V").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GraphqlResponse
     */
    public function getInfoGraph(
        $mediaCode,
        $child_comment_count = 3,
        $fetch_comment_count = 40,
        $parent_comment_count = 24) 
    {
        return $request = $this->ig->request("graphql/query/")
            ->setVersion(5)
            ->setSignedPost(false)
            ->addParam('query_hash', '55a3c4bad29e4e20c20ff4cdfd80f5b4')
            ->addParam('variables', json_encode([
                "shortcode" => $mediaCode,
                "child_comment_count" => $child_comment_count,
                "fetch_comment_count" => $fetch_comment_count,
                "parent_comment_count" => $parent_comment_count,
                "has_threaded_comments" => true,
            ]))
            ->getResponse(new Response\GraphqlResponse());
    }

    /**
     * Get OEMBED info.
     *
     * @param string $mediaUrl Instagram webs media format. Example: https://www.instagram.com/p/ABCDEFGH.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaInfoResponse
     */
    public function getOembedInfo(
        $mediaUrl)
    {
        return $this->ig->request('oembed/')
            ->setIsSilentFail(true)
            ->addParam('url', $mediaUrl)
            ->getResponse(new Response\MediaInfoResponse());
    }

    /**
     * Delete a media item.
     *
     * @param string     $mediaId   The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string|int $mediaType The type of the media item you are deleting. One of: "PHOTO", "VIDEO"
     *                              "CAROUSEL", or the raw value of the Item's "getMediaType()" function.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaDeleteResponse
     */
    public function delete(
        $mediaId,
        $mediaType = 'PHOTO')
    {
        $mediaType = Utils::checkMediaType($mediaType);

        return $this->ig->request("media/{$mediaId}/delete/")
            ->addParam('media_type', $mediaType)
            ->addPost('igtv_feed_preview', false)
            ->addPost('media_id', $mediaId)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_uuid', $this->ig->uuid)
            ->getResponse(new Response\MediaDeleteResponse());
    }

    /**
     * Edit media.
     *
     * @param string     $mediaId     The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string     $captionText Caption to use for the media.
     * @param array|null $metadata    (optional) Associative array of optional metadata to edit:
     *                                "usertags" - special array with user tagging instructions,
     *                                if you want to modify the user tags;
     *                                "location" - a Location model object to set the media location,
     *                                or boolean FALSE to remove any location from the media.
     * @param string|int $mediaType   The type of the media item you are editing. One of: "PHOTO", "VIDEO"
     *                                "CAROUSEL", or the raw value of the Item's "getMediaType()" function.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\EditMediaResponse
     *
     * @see Usertag::tagMedia() for an example of proper "usertags" metadata formatting.
     * @see Usertag::untagMedia() for an example of proper "usertags" metadata formatting.
     */
    public function edit(
        $mediaId,
        $captionText = '',
        array $metadata = null,
        $mediaType = 'PHOTO')
    {
        $mediaType = Utils::checkMediaType($mediaType);

        $request = $this->ig->request("media/{$mediaId}/edit_media/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('caption_text', $captionText);

        if (isset($metadata['usertags'])) {
            Utils::throwIfInvalidUsertags($metadata['usertags']);
            $request->addPost('usertags', json_encode($metadata['usertags']));
        }

        if (isset($metadata['location'])) {
            if ($metadata['location'] === false) {
                // The user wants to remove the current location from the media.
                $request->addPost('location', '{}');
            } else {
                // The user wants to add/change the location of the media.
                if (!$metadata['location'] instanceof Response\Model\Location) {
                    throw new \InvalidArgumentException('The "location" metadata value must be an instance of \InstagramAPI\Response\Model\Location.');
                }

                $request
                    ->addPost('location', Utils::buildMediaLocationJSON($metadata['location']))
                    ->addPost('geotag_enabled', '1')
                    ->addPost('posting_latitude', $metadata['location']->getLat())
                    ->addPost('posting_longitude', $metadata['location']->getLng())
                    ->addPost('media_latitude', $metadata['location']->getLat())
                    ->addPost('media_longitude', $metadata['location']->getLng());

                if ($mediaType === 'CAROUSEL') { // Albums need special handling.
                    $request
                        ->addPost('exif_latitude', 0.0)
                        ->addPost('exif_longitude', 0.0);
                } else { // All other types of media use "av_" instead of "exif_".
                    $request
                        ->addPost('av_latitude', 0.0)
                        ->addPost('av_longitude', 0.0);
                }
            }
        }

        return $request->getResponse(new Response\EditMediaResponse());
    }

    /**
     * Like a media item.
     *
     * @param string $mediaId        The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param int    $feedPosition   The position of the media in the feed.
     * @param string $module         (optional) From which app module (page) you're performing this action.
     * @param bool   $carouselBumped (optional) If the media is carousel bumped.
     * @param array  $extraData      (optional) Depending on the module name, additional data is required.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     *
     * @see Media::_parseLikeParameters() For all supported modules and required parameters.
     */
    public function like(
        $mediaId,
        $feedPosition = 0,
        $module = 'feed_timeline',
        $carouselBumped = false,
        array $extraData = [])
    {
        $request = $this->ig->request("media/{$mediaId}/like/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('device_id', $this->ig->device_id)
            ->addPost('media_id', $mediaId)
            ->addPost('container_module', $module)
            ->addPost('radio_type', $this->ig->radio_type)
            ->addPost('feed_position', $feedPosition)
            ->addPost('is_carousel_bumped_post', $carouselBumped);
            

        if (isset($extraData['carousel_media'])) {
            $request->addPost('carousel_index', $extraData['carousel_index']);
        }

        $extraData['media_id'] = $mediaId;
        $this->_parseLikeParameters('like', $request, $module, $extraData);

        return $request->getResponse(new Response\GenericResponse());
    }

    /**
     * Unlike a media item.
     *
     * @param string $mediaId   The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string $module    (optional) From which app module (page) you're performing this action.
     * @param array  $extraData (optional) Depending on the module name, additional data is required.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     *
     * @see Media::_parseLikeParameters() For all supported modules and required parameters.
     */
    public function unlike(
        $mediaId,
        $module = 'feed_timeline',
        array $extraData = [])
    {
        $request = $this->ig->request("media/{$mediaId}/unlike/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('media_id', $mediaId)
            ->addPost('radio_type', $this->ig->radio_type)
            ->addPost('module_name', $module);

        $this->_parseLikeParameters('unlike', $request, $module, $extraData);

        return $request->getResponse(new Response\GenericResponse());
    }

    /**
     * Get feed of your liked media.
     *
     * @param string|null $maxId Next "maximum ID", used for pagination.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\LikeFeedResponse
     */
    public function getLikedFeed(
        $maxId = null)
    {
        $request = $this->ig->request('feed/liked/');
        if ($maxId !== null) {
            $request->addParam('max_id', $maxId);
        }

        return $request->getResponse(new Response\LikeFeedResponse());
    }

    /**
     * Get list of users who liked a media item (with Web API)
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GraphqlResponse
     */
    public function getLikersGraph(
        $shortcode,
        $rollout_hash,
        $next_page = 12,
        $end_cursor = null,
        $include_reel = false)
    {
        if ($shortcode == null) {
            throw new \InvalidArgumentException('Empty $shortcode sent to getLikersGraph() function.');
        }

        if ($rollout_hash == null) {
            throw new \InvalidArgumentException('Empty $rollout_hash sent to getLikersGraph() function.');
        }

        $request = $this->ig->request("https://www.instagram.com/graphql/query/")
            ->setAddDefaultHeaders(false)
            ->setSignedPost(false)
            ->setIsBodyCompressed(false)
            ->addHeader('X-CSRFToken', $this->ig->client->getToken())
            ->addHeader('Referer', 'https://www.instagram.com/p/' . $shortcode . "/")
            ->addHeader('Host', 'www.instagram.com')
            ->addHeader('X-Requested-With', 'XMLHttpRequest')
            ->addHeader('X-Instagram-AJAX', $rollout_hash)
            ->addHeader('X-IG-App-ID', Constants::IG_WEB_APPLICATION_ID)
            ->addHeader('X-IG-WWW-Claim', Constants::X_IG_WWW_CLAIM);
            $request->addHeader('User-Agent', sprintf('Mozilla/5.0 (Linux; Android %s; Google) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36', $this->ig->device->getAndroidRelease()));
            $request->addParam('query_hash', 'd5d763b1e2acf209d62d22d184488e57')
                    ->addParam('variables', json_encode([
                        "shortcode" => $shortcode,
                        "include_reel" => $include_reel ? true : false,
                        "first" => $next_page,
                        "after" => $end_cursor
                    ]));
            return $request->getResponse(new Response\GraphqlResponse());
    }

    /**
     * Get list of users who liked a media item.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaLikersResponse
     */
    public function getLikers(
        $mediaId)
    {
		$this->ig->connectionSpeed = rand(2000, 10000) . 'kbps';
        $this->ig->client->_totalBytes = '2463193';
        $this->ig->client->_totalTime = rand(100,600);
        return $this->ig->request("media/{$mediaId}/likers/")->getResponse(new Response\MediaLikersResponse());
    }

    /**
     * Get a simplified, chronological list of users who liked a media item.
     *
     * WARNING! DANGEROUS! Although this function works, we don't know
     * whether it's used by Instagram's app right now. If it isn't used by
     * the app, then you can easily get BANNED for using this function!
     *
     * If you call this function, you do that AT YOUR OWN RISK and you
     * risk losing your Instagram account! This notice will be removed if
     * the function is safe to use. Otherwise this whole function will
     * be removed someday, if it wasn't safe.
     *
     * Only use this if you are OK with possibly losing your account!
     *
     * TODO: Research when/if the official app calls this function.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaLikersResponse
     */
    public function getLikersChrono(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/likers_chrono/")->getResponse(new Response\MediaLikersResponse());
    }

    /**
     * Enable comments for a media item.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     */
    public function enableComments(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/enable_comments/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->setSignedPost(false)
            ->getResponse(new Response\GenericResponse());
    }

    /**
     * Disable comments for a media item.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     */
    public function disableComments(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/disable_comments/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->setSignedPost(false)
            ->getResponse(new Response\GenericResponse());
    }

    /**
     * Post a comment on a media item.
     *
     * @param string      $mediaId        The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string      $commentText    Your comment text.
     * @param string|null $replyCommentId (optional) The comment ID you are replying to, if this is a reply (ie "17895795823020906");
     *                                    when replying, your $commentText MUST contain an @-mention at the start (ie "@theirusername Hello!").
     * @param string      $module         (optional) From which app module (page) you're performing this action.
     *                                    "comments_v2" - In App: clicking on comments button,
     *                                    "self_comments_v2" - In App: commenting on your own post,
     *                                    "comments_v2_feed_timeline" - Unknown,
     *                                    "comments_v2_feed_contextual_hashtag" - Unknown,
     *                                    "comments_v2_photo_view_profile" - Unknown,
     *                                    "comments_v2_video_view_profile" - Unknown,
     *                                    "comments_v2_media_view_profile" - Unknown,
     *                                    "comments_v2_feed_contextual_location" - Unknown,
     *                                    "modal_comment_composer_feed_timeline" - In App: clicking on prompt from timeline.
     * @param int         $carouselIndex  (optional) The image selected in a carousel while liking an image.
     * @param int         $feedPosition   (optional) The position of the media in the feed.
     * @param bool        $feedBumped     (optional) If Instagram bumped this post to the top of your feed.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentResponse
     */
    public function comment(
        $mediaId,
        $commentText,
        $replyCommentId = null,
        $module = 'comments_v2',
        $carouselIndex = 0,
        $feedPosition = 0,
        $feedBumped = false)
    {
        $request = $this->ig->request("media/{$mediaId}/comment/")
            ->addPost('user_breadcrumb', Utils::generateUserBreadcrumb(mb_strlen($commentText)))
            ->addPost('idempotence_token', Signatures::generateUUID())
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('comment_text', $commentText)
            ->addPost('container_module', $module)
            ->addPost('radio_type', $this->ig->radio_type)
            ->addPost('device_id', $this->ig->device_id)
            ->addPost('carousel_index', $carouselIndex)
            ->addPost('feed_position', $feedPosition)
            ->addPost('is_carousel_bumped_post', $feedBumped);
        if ($replyCommentId !== null) {
            $request->addPost('replied_to_comment_id', $replyCommentId);
        }

        return $request->getResponse(new Response\CommentResponse());
    }

    /**
     * Post a comment on a media item.
     * 
     * @param string      $mediaId        The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string      $commentText    Your comment text.
     * @param string      $rollout_hash   Use function getDataFromWeb() from /src/Instagram.php to get this constant
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentResponse
     */
    public function commentWeb(
        $mediaId,
        $commentText,
        $rollout_hash)
    {
        if ($mediaId == null) {
            throw new \InvalidArgumentException('Empty $mediaId sent to commentWeb() function.');
        }

        if ($commentText == null) {
            throw new \InvalidArgumentException('Empty $commentText sent to commentWeb() function.');
        }

        if ($rollout_hash == null || !is_string($rollout_hash)) {
            throw new \InvalidArgumentException('Empty or incorrect $rollout_hash sent to commentWeb() function.');
        }

        $request = $this->ig->request("https://www.instagram.com/web/comments/{$mediaId}/add/")
            ->setAddDefaultHeaders(false)
            ->setSignedPost(false)
            ->addHeader('X-CSRFToken', $this->ig->client->getToken())
            ->addHeader('Referer', 'https://www.instagram.com/')
            ->addHeader('Host', 'www.instagram.com')
            ->addHeader('X-Requested-With', 'XMLHttpRequest')
            ->addHeader('X-Instagram-AJAX', $rollout_hash)
            ->addHeader('X-IG-App-ID', Constants::IG_WEB_APPLICATION_ID)
            ->addHeader('X-IG-WWW-Claim', Constants::X_IG_WWW_CLAIM)
            ->addHeader('User-Agent', sprintf('Mozilla/5.0 (Linux; Android %s; Google) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36', $this->ig->device->getAndroidRelease()))
            ->addPost('comment_text', $commentText);

        return $request->getResponse(new Response\CommentResponse());
    }

    /**
     * Get media comments.
     *
     * Note that this endpoint supports both backwards and forwards pagination.
     * The only one you should really care about is "max_id" for backwards
     * ("load older comments") pagination in normal cases. By default, if no
     * parameter is provided, Instagram gives you the latest page of comments
     * and then paginates backwards via the "max_id" parameter (and the correct
     * value for it is the "next_max_id" in the response).
     *
     * However, if you come to the comments "from a Push notification" (uses the
     * "target_comment_id" parameter), then the response will ALSO contain a
     * "next_min_id" value. In that case, you can get newer comments (than the
     * target comment) by using THAT value and the "min_id" parameter instead.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param array  $options An associative array of optional parameters, including:
     *                        "max_id" - next "maximum ID" (get older comments, before this ID), used for backwards pagination;
     *                        "min_id" - next "minimum ID" (get newer comments, after this ID), used for forwards pagination;
     *                        "target_comment_id" - used by comment Push notifications to retrieve the page with the specific comment.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaCommentsResponse
     */
    public function getComments(
        $mediaId,
        array $options = [])
    {
        $request = $this->ig->request("media/{$mediaId}/comments/")
            ->addParam('can_support_threading', true);

        // Pagination.
        if (isset($options['min_id']) && isset($options['max_id'])) {
            throw new \InvalidArgumentException('You can use either "min_id" or "max_id", but not both at the same time.');
        }
        if (isset($options['min_id'])) {
            $request->addParam('min_id', $options['min_id']);
        }
        if (isset($options['max_id'])) {
            $request->addParam('max_id', $options['max_id']);
        }

        // Request specific comment (does NOT work together with pagination!).
        // NOTE: If you try pagination params together with this param, then the
        // server will reject the request completely and give nothing back!
        if (isset($options['target_comment_id'])) {
            if (isset($options['min_id']) || isset($options['max_id'])) {
                throw new \InvalidArgumentException('You cannot use the "target_comment_id" parameter together with the "min_id" or "max_id" parameters.');
            }
            $request->addParam('target_comment_id', $options['target_comment_id']);
        }

        return $request->getResponse(new Response\MediaCommentsResponse());
    }

    /**
     * Get summary information about comments.
     *
     * @param string|string[] $mediaIds One or more media IDs in Instagram's internal format (ie "3482384834_43294"). Can be an array of strings or a single string.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentInfosResponse
     */
    public function getCommentInfos(
        $mediaIds)
    {
        if ($mediaIds === null) {
            throw new \InvalidArgumentException('You can not pass null to mediaIds!');
        }
        if (is_array($mediaIds)) {
            $mediaIds = implode(',', $mediaIds);
        }
        return $this->ig->request('media/comment_infos')
            ->addParam('media_ids', $mediaIds)
            ->getResponse(new Response\CommentInfosResponse());
    }

    /**
     * Get the replies to a specific media comment.
     *
     * You should be sure that the comment actually HAS more replies before
     * calling this endpoint! In that case, the comment itself will have a
     * non-zero "child comment count" value, as well as some "preview comments".
     *
     * If the number of preview comments doesn't match the full "child comments"
     * count, then you are ready to call this endpoint to retrieve the rest of
     * them. Do NOT call it frivolously for comments that have no child comments
     * or where you already have all of them via the child comment previews!
     *
     * @param string $mediaId   The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string $commentId The parent comment's ID.
     * @param array  $options   An associative array of optional parameters, including:
     *                          "max_id" - next "maximum ID" (get older comments, before this ID), used for backwards pagination;
     *                          "min_id" - next "minimum ID" (get newer comments, after this ID), used for forwards pagination.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MediaCommentRepliesResponse
     */
    public function getCommentReplies(
        $mediaId,
        $commentId,
        array $options = [])
    {
        $request = $this->ig->request("media/{$mediaId}/comments/{$commentId}/inline_child_comments/");

        if (isset($options['min_id'], $options['max_id'])) {
            throw new \InvalidArgumentException('You can use either "min_id" or "max_id", but not both at the same time.');
        }

        if (isset($options['max_id'])) {
            $request->addParam('max_id', $options['max_id']);
        } elseif (isset($options['min_id'])) {
            $request->addParam('min_id', $options['min_id']);
        }

        return $request->getResponse(new Response\MediaCommentRepliesResponse());
    }

    /**
     * Delete a comment.
     *
     * @param string $mediaId   The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string $commentId The comment's ID.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\DeleteCommentResponse
     */
    public function deleteComment(
        $mediaId,
        $commentId)
    {
        return $this->ig->request("media/{$mediaId}/comment/{$commentId}/delete/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->getResponse(new Response\DeleteCommentResponse());
    }

    /**
     * Delete multiple comments.
     *
     * @param string          $mediaId    The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string|string[] $commentIds The IDs of one or more comments to delete.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\DeleteCommentResponse
     */
    public function deleteComments(
        $mediaId,
        $commentIds)
    {
        if (is_array($commentIds)) {
            $commentIds = implode(',', $commentIds);
        }

        return $this->ig->request("media/{$mediaId}/comment/bulk_delete/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('comment_ids_to_delete', $commentIds)
            ->getResponse(new Response\DeleteCommentResponse());
    }

    /**
     * Like a comment.
     *
     * @param string $commentId    The comment's ID.
     * @param int    $feedPosition The position of the media item in the feed.
     * @param string $module       From which module you're preforming this action.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentLikeUnlikeResponse
     */
    public function likeComment(
        $commentId,
        $feedPosition,
        $module = 'self_comments_v2')
    {
        return $this->ig->request("media/{$commentId}/comment_like/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('is_carousel_bumped_post', false)
            ->addPost('container_module', $module)
            ->addPost('feed_position', $feedPosition)
            ->getResponse(new Response\CommentLikeUnlikeResponse());
    }

    /**
     * Unlike a comment.
     *
     * @param string $commentId    The comment's ID.
     * @param int    $feedPosition The position of the media item in the feed.
     * @param string $module       From which module you're preforming this action.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentLikeUnlikeResponse
     */
    public function unlikeComment(
        $commentId,
        $feedPosition,
        $module = 'self_comments_v2')
    {
        return $this->ig->request("media/{$commentId}/comment_unlike/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('is_carousel_bumped_post', false)
            ->addPost('container_module', $module)
            ->addPost('feed_position', $feedPosition)
            ->getResponse(new Response\CommentLikeUnlikeResponse());
    }

    /**
     * Get list of users who liked a comment.
     *
     * @param string $commentId The comment's ID.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentLikersResponse
     */
    public function getCommentLikers(
        $commentId)
    {
        return $this->ig->request("media/{$commentId}/comment_likers/")->getResponse(new Response\CommentLikersResponse());
    }

    /**
     * Translates comments and/or media captions.
     *
     * Note that the text will be translated to American English (en-US).
     *
     * @param string|string[] $commentIds The IDs of one or more comments and/or media IDs
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\TranslateResponse
     */
    public function translateComments(
        $commentIds)
    {
        if (is_array($commentIds)) {
            $commentIds = implode(',', $commentIds);
        }

        return $this->ig->request("language/bulk_translate/?comment_ids={$commentIds}")
            ->getResponse(new Response\TranslateResponse());
    }

    /**
     * Get information comment settings from a media ID.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\CommentInfoResponse
     */
    public function getCommentInfo(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/comment_info/")
            ->getResponse(new Response\CommentInfoResponse());
    }

    /**
     * Validate a web URL for acceptable use as external link.
     *
     * This endpoint lets you check if the URL is allowed by Instagram, and is
     * helpful to call before you try to use a web URL in your media links.
     *
     * @param string $url The URL you want to validate.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\ValidateURLResponse
     */
    public function validateURL(
        $url)
    {
        return $this->ig->request('media/validate_reel_url/')
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->addPost('url', $url)
            ->getResponse(new Response\ValidateURLResponse());
    }

    /**
     * Save a media item.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\SaveAndUnsaveMedia
     */
    public function save(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/save/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->getResponse(new Response\SaveAndUnsaveMedia());
    }

    /**
     * Unsave a media item.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\SaveAndUnsaveMedia
     */
    public function unsave(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/unsave/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->getResponse(new Response\SaveAndUnsaveMedia());
    }

    /**
     * Get saved media items feed.
     *
     * @param string|null $maxId Next "maximum ID", used for pagination.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\SavedFeedResponse
     */
    public function getSavedFeed(
        $maxId = null)
    {
        $request = $this->ig->request('feed/saved/');
        if ($maxId !== null) {
            $request->addParam('max_id', $maxId);
        }

        return $request->getResponse(new Response\SavedFeedResponse());
    }

    /**
     * Get blocked media.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\BlockedMediaResponse
     */
    public function getBlockedMedia()
    {
        return $this->ig->request('media/blocked/')
            ->getResponse(new Response\BlockedMediaResponse());
    }

    /**
     * Report media as spam.
     *
     * @param string $mediaId    The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string $sourceName (optional) Source of the media.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     */
    public function report(
        $mediaId,
        $sourceName = 'feed_contextual_chain')
    {
        return $this->ig->request("media/{$mediaId}/flag_media/")
            ->addPost('media_id', $mediaId)
            ->addPost('source_name', $sourceName)
            ->addPost('reason_id', '1')
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->getResponse(new Response\GenericResponse());
    }

    /**
     * Report a media comment as spam.
     *
     * @param string $mediaId   The media ID in Instagram's internal format (ie "3482384834_43294").
     * @param string $commentId The comment's ID.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     */
    public function reportComment(
        $mediaId,
        $commentId)
    {
        return $this->ig->request("media/{$mediaId}/comment/{$commentId}/flag/")
            ->addPost('media_id', $mediaId)
            ->addPost('comment_id', $commentId)
            ->addPost('reason', '1')
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_csrftoken', $this->ig->client->getToken())
            ->getResponse(new Response\GenericResponse());
    }

    /**
     * Get media permalink.
     *
     * @param string $mediaId The media ID in Instagram's internal format (ie "3482384834_43294").
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\PermalinkResponse
     */
    public function getPermalink(
        $mediaId)
    {
        return $this->ig->request("media/{$mediaId}/permalink/")
            ->addParam('share_to_app', 'copy_link')
            ->getResponse(new Response\PermalinkResponse());
    }

    /**
     * Validate and update the parameters for a like or unlike request.
     *
     * @param string  $type      What type of request this is (can be "like" or "unlike").
     * @param Request $request   The request to fill with the parsed data.
     * @param string  $module    From which app module (page) you're performing this action.
     * @param array   $extraData Depending on the module name, additional data is required.
     *
     * @throws \InvalidArgumentException
     */
    protected function _parseLikeParameters(
        $type,
        Request $request,
        $module,
        array $extraData)
    {
        // Is this a "double-tap to like"? Note that Instagram doesn't have
        // "double-tap to unlike". So this can only be "1" if it's a "like".
        if ($type === 'like' && isset($extraData['double_tap']) && $extraData['double_tap']) {
            $request->addUnsignedPost('d', 1);
        } else {
            $request->addUnsignedPost('d', 0); // Must always be 0 for "unlike".
        }

        // Now parse the necessary parameters for the selected module.
        switch ($module) {
        case 'feed_contextual_post': 
        case 'feed_contextual_chain':
            // "Explore" tab.
            if (isset($extraData['explore_source_token'])) {
                // The explore media `Item::getExploreSourceToken()` value.
                $request->addPost('explore_source_token', $extraData['explore_source_token'])
                        ->addPost('chaining_session_id', Signatures::generateUUID())
                        ->addPost('parent_m_pk', $extraData['media_id']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            break;
        case 'profile': // LIST VIEW (when posts are shown vertically by the app
                        // one at a time (as in the Timeline tab)): Any media on
                        // a user profile (their timeline) in list view mode.
        case 'media_view_profile': // GRID VIEW (standard 3x3): Album (carousel)
                                   // on a user profile (their timeline).
        case 'video_view_profile': // GRID VIEW (standard 3x3): Video on a user
                                   // profile (their timeline).
        case 'photo_view_profile': // GRID VIEW (standard 3x3): Photo on a user
                                   // profile (their timeline).
            if (isset($extraData['username']) && isset($extraData['user_id'])) {
                // Username and id of the media's owner (the profile owner).
                $request->addPost('username', $extraData['username'])
                    ->addPost('user_id', $extraData['user_id']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            break;
        case 'feed_contextual_hashtag': // "Hashtag" search result.
            if (isset($extraData['hashtag'])) {
                // The hashtag where the app found this media.
                Utils::throwIfInvalidHashtag($extraData['hashtag']);
                $request->addPost('hashtag_name', $extraData['hashtag']);
                // $request->addPost('hashtag_id', $extraData['hashtag_id']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            break;
        case 'hashtag_immersive_viewer':
        case 'explore_video_chaining':
        case 'explore_event_viewer':
            if (isset($extraData['chaining_session_id'])) {
                $request->addPost('chaining_session_id', $extraData['chaining_session_id']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            break;
        case 'feed_contextual_location': // "Location" search result.
            if (isset($extraData['location_id'])) {
                // The location ID of this media.
                $request->addPost('location_id', $extraData['location_id']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            break;
        case 'newsfeed': // "Followings Activity" feed tab. Used when
                         // liking/unliking a post that we clicked on from a
                         // single-activity "xyz liked abc's post" entry.
        case 'feed_short_url': // When the like is done from a short URL media.
                               // Example: https://www.instagram.com/p/abcdefhij1234/.
        case 'feed_contextual_newsfeed_multi_media_liked':  // "Followings
        // Activity" feed
        // tab. Used when
        // liking/unliking a
        // post that we
        // clicked on from a
        // multi-activity
        // "xyz liked 5
        // posts" entry.
        case 'igtv_profile': // Go to a user profile and click at their IGTV icon.
        case 'igtv_explore_grid':
        case 'igtv_explore_pinned_nav': // Go to Explore and then IGTV section.
            break;
        case 'feed_timeline': // "Timeline" tab (the global Home-feed with all
                                // kinds of mixed news).
        case 'feed_contextual_profile':
        case 'igtv_feed_timeline': // IGTV timeline feed.
            $request->addPost('inventory_source', 'media_or_ad');
            break;
        case 'instagram_shopping_home_creators_contextual_feed':
        case 'instagram_shopping_home_checkout_contextual_feed':
            if (isset($extraData['topic_cluster_id']) && isset($extraData['topic_cluster_type'])
                && isset($extraData['topic_cluster_session_id']) && isset($extraData['topic_cluster_title'])
                && isset($extraData['topic_nav_order'])) {
                // Cluster ID. Example: shopping:0.
                $request->addPost('topic_cluster_id', $extraData['topic_cluster_id']);
                // Cluster type. Example: shopping.
                $request->addPost('topic_cluster_type', $extraData['topic_cluster_type']);
                // Cluster session ID. UUID.
                $request->addPost('topic_cluster_session_id', $extraData['topic_cluster_session_id']);
                // Cluster title. Example: Shop
                $request->addPost('topic_cluster_title', $extraData['topic_cluster_title']);
                // Topic Nav Order. Int.
                $request->addPost('topic_nav_order', $extraData['topic_nav_order']);
            } else {
                throw new \InvalidArgumentException(sprintf('Missing extra data for module "%s".', $module));
            }
            // no break
        default:
            throw new \InvalidArgumentException(sprintf('Invalid module name. %s does not correspond to any of the valid module names.', $module));
        }
    }

    /**
     * Section: Hypervoter functions
     */
    
    public function likeWebs(
        $mediaId,
        $postcode = null,
        $rollout_hash = 'f9e28d162740')
    {
        if ($mediaId == null) {
            throw new \InvalidArgumentException('Empty $mediaId sent to likeWeb() function.');
        }

        if ($rollout_hash == null || !is_string($rollout_hash)) {
            throw new \InvalidArgumentException('Empty or incorrect $rollout_hash sent to likeWeb() function.');
        }

        $request = $this->ig->request("https://instagram.com/web/likes/{$mediaId}/like/")
            ->setAddDefaultHeaders(false)
            ->setSignedPost(false)
            ->addHeader('X-CSRFToken', $this->ig->client->getToken())
            ->addHeader('Referer', 'https://www.instagram.com/' . $postcode . '/')
            ->addHeader('X-Requested-With', 'XMLHttpRequest')
            ->addHeader('X-IG-Connection-Type', 'WiFi')
            ->addHeader('X-IG-Connection-Speed', '1432kbps')
            ->addHeader('Accept', '*/*')
            ->addHeader('Host', 'i.instagram.com')
            ->addHeader('X-Instagram-AJAX', $rollout_hash)
            ->addHeader('X-IG-App-ID', '1217981644879628')
            ->addHeader('sec-fetch-mode', 'cors')
            ->addHeader('sec-fetch-dest', 'empty')
            ->addHeader('origin', 'https://www.instagram.com')
            ->addHeader('accept-encoding', 'gzip, deflate, br')
            ->addHeader('x-ig-www-claim', 'hmac.AR0wW9PSDNz5VSoxDtEeeugeDX-ntKppg1vvRYROK7RqAh5T')
            ->addHeader('Accept-Language', 'en-RO;q=1')
            ->addHeader('sec-fetch-site', 'same-origin')
            ->addHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/83.0.4103.88 Mobile/15E148 Safari/604.1')
            ->addPost('', '');

        return $request->getResponse(new Response\GenericResponse());
    }
}
