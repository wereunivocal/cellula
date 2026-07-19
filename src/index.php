<?php $b = "bloginfo" ?>
<!DOCTYPE html>
<html <?php language_attributes() ?>>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="Content-Type"
          content="<?php $b('html_type') ?>; charset=<?php $b('charset') ?>"/>
    <title><?= wp_get_document_title() ?></title>
    <style>
        #footer {
            margin: 2.75em auto
        }

        body {
            font-family: system-ui, sans-serif;
            margin: 1.75em auto;
            max-width: 80vw;
            line-height: 1.6;
            color: #444;
            padding: 0 .75em
        }

        main {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: start;
        }

        @media (min-width: 768px) {
            main {
                display: grid;
                grid-template:"s a" auto / 3fr 1fr;
            }
        }

        section {
            display: grid;
            row-gap: 1em;
            grid-area: s;
            align-items: start
        }

        #sidebar {
            grid-area: a
        }

        #sidebar > ul,
        #sidebar li {
            list-style-type: none
        }

        #commentform > p:not(.comment-form-cookies-consent) {
            display: flex;
            flex-direction: column
        }

        article {
            word-break: break-all;
            display: grid
        }

        body > footer {
            text-align: center;
            font-size: .75em
        }

        img {
            max-width: 100%;
            height: auto
        }

        h1, h2, h3, h4, h5, h6 {
            line-height: 1.2;
            margin: 1.5em 0 0
        }
    </style>
    <link rel="pingback" href="<?php $b('pingback_url') ?>"/>
    <?php if (is_singular()) wp_enqueue_script('comment-reply');
    wp_head() ?>
</head>
<body <?php body_class() ?>>
<header>
    <h1><a href="<?= home_url() ?>"><?php $b('name') ?></a></h1>
    <div class="description"><?php $b('description') ?></div>
</header>
<main>
    <?php if (have_posts()) { ?>
        <section><?php while (have_posts()) {
            the_post() ?>
            <article>
            <?php the_title('<h1>', '</h1>');
            if (is_singular()) {
                the_content();
                the_posts_navigation() ?>
                <address class="author"><?php _e('By: ', 'cg');
                    the_author_link() ?></address>
                <?= __('Published on: ', 'cg') ?>
                <time><?php the_date() ?></time>
                <?php if (comments_open() || get_comments_number()) {
                    ?>
                    <footer class="comments">
                    <?php if (post_password_required()) {
                        return;
                    }
                    ?>
                    <h2><?php printf(_n('A comment on &ldquo;%2$s&rdquo;', '%1$s comments on &ldquo;%2$s&rdquo;', get_comments_number(), 'cg'), number_format_i18n(get_comments_number()), '<em>' . get_the_title() . '</em>') ?></h2>
                    <?php $c = get_comments(array('post_id' => get_the_id()));
                    wp_list_comments(
                        [
                        'avatar_size' => 120,
                        'style' => 'div',
                        'callback' => function ($c, $a, $d) {
                        ?>
                            <aside <?php comment_class() ?>id="comment-<?php comment_ID() ?>">
                            <?php if ($c->comment_approved == '0') { ?>
                                <p>
                                    <em><?php esc_html_e('Your comment is awaiting moderation.', 'cg') ?></em>
                                </p>
                            <?php } ?>
                                <h3>
                                    <time><?php __('on: ', 'cg') . printf(esc_html__('%1$s at %2$s ', 'cg'), get_comment_date(), get_comment_time()) ?></time>
                                    <span><?= get_comment_author() . __(' said:', 'cg') ?></span>
                                </h3>
                                <p><?php comment_text() ?></p>
                                <a href="#">
                                    <?php comment_reply_link(array_merge($a, array('depth' => $d, 'max_depth' => $a['max_depth']))) ?>
                                </a>
                            </aside>
                            <?php
                        }], $c);
                    the_comments_navigation();
                    comment_form() ?>
                    </footer>
                <?php
                }
            } else {
                the_excerpt(); ?>
                <a href="<?php the_permalink() ?>"><?= __('Read more', 'cg') ?></a><?php
            }
            ?></article><?php
        }
        ?>
        </section>
    <?php get_sidebar();
    }
    do_action('get_footer', '', []);
    wp_footer() ?>
</main>
<footer id="footer" role="contentinfo"><p><?php
        printf(__('%1$s is powered by %2$s'), get_bloginfo('name'), '<a href="https://wp.org">WordPress</a>') ?></p>
</footer>
</body>
</html><?php


