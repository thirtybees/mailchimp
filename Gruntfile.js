module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'mdstripe.zip'
                },
                files: [
                    {src: ['classes/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['controllers/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'mdstripe/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'mdstripe/'},
                    {src: 'index.php', dest: 'mdstripe/'},
                    {src: 'mdstripe.php', dest: 'mdstripe/'},
                    {src: 'cloudunlock.php', dest: 'mdstripe/'},
                    {src: 'logo.png', dest: 'mdstripe/'},
                    {src: 'logo.gif', dest: 'mdstripe/'},
                    {src: 'LICENSE.md', dest: 'mdstripe/'},
                    {src: 'CONTRIBUTORS.md', dest: 'mdstripe/'},
                    {src: 'README.md', dest: 'mdstripe/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};
