module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'mailchimp.zip'
                },
                files: [
                    {src: ['classes/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['controllers/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'mailchimp/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'mailchimp/'},
                    {src: 'index.php', dest: 'mailchimp/'},
                    {src: 'mailchimp.php', dest: 'mailchimp/'},
                    {src: 'cli.php', dest: 'mailchimp/'},
                    {src: 'logo.png', dest: 'mailchimp/'},
                    {src: 'logo.gif', dest: 'mailchimp/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};
