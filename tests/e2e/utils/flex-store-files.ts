/** What a Flex store wrote to the local wp-env instance's uploads for one post. */

import { wpEval, wpEvalJson } from '@utils/wp-cli'

const storeDir = (postId: number): string =>
  `trailingslashit(wp_upload_dir()['basedir']) . 'ceros-flex/${Math.trunc(postId)}'`

/** Paths under the post's store folder, relative to it and sorted; [] when nothing is stored. */
export const listStoredFiles = (postId: number): Promise<string[]> =>
  wpEvalJson<string[]>(
    `(function ($dir) {
      if (!is_dir($dir)) { return []; }
      $files = [];
      $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
      foreach ($items as $item) { $files[] = substr($item->getPathname(), strlen($dir) + 1); }
      sort($files);
      return $files;
    })(${storeDir(postId)})`,
  )

/** Delete the post's store folder. Deleting the post leaves it behind. */
export const removeStoredFiles = (postId: number): Promise<void> =>
  wpEval(`ceros_store_rrmdir(${storeDir(postId)});`)
