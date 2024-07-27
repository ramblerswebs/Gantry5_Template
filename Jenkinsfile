pipeline {
  agent any
  parameters {
        string(name: 'BINARY_STORE', defaultValue: '/Binaries', trim: true)
  }
  stages {
    stage('Update version information') {
      steps {
        sh 'python3 /home/UpdateJoomlaBuild -bx -i tpl_hydrogen_ramblers.xml'
      }
    }
    stage('Package Zip File') {
      steps {
        // First Zip the contents
        sh 'zip -r tpl_hydrogen_ramblers.zip .'
      }
    }
    stage('Repository Store') {
    	steps {
    	  script {
    	      dir('tmp'){
    	        sh 'rm -f *.zip'
    	      }
          }
        sh 'python3 /home/UpdateJoomlaBuild -bx -i tpl_hydrogen_ramblers.xml -z tmp'    	  
        fileOperations([fileCopyOperation(excludes: '', flattenFiles: true, includes: 'tmp/*.zip', targetLocation: params.BINARY_STORE)])
    	}
    }
  } // End of Stages
  post {
  	always {
  	    echo "Completed"
  	}
  	success {
  		echo "Completed Succcessfully"
  		cleanWs()
  	}
  	failure {
  	    echo "Completed with Failure"
  	}
  	unstable {
  	    echo "Unstable Build"
  	}
  	changed {
  	    echo "Compeleted with Changes"
  	}
  } // End of Post
} // End of Pipeline
