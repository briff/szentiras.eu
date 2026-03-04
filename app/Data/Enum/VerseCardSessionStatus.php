<?php

namespace SzentirasHu\Data\Enum;

/**
 * This is the flow of statuses for a verse card session. This is all about searching and downloading images, and rendering the final image.
 * 1. Initializing: It is the first state before and during sending a request to the image provider.
 * 2. Downloading: The image provider responded with metadata, and the system is downloading the images in the background. Here the metadata is available, so the UI can already show placeholders and metadata.
 * 3. Choosing: The images has been downloaded, and the user can choose which one to use, no background processes running here.
 */

enum VerseCardSessionStatus: string
{
    case Initializing = 'initializing';
    case Downloading = 'downloading';
    case Choosing = 'choosing';
    case Rendering = 'rendering';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';
    case Ended = 'ended';
}
