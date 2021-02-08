<?php
namespace RLKT\Pak{

	class Utils
	{
		public static function toNull($count){
			return pack("x".$count);
		}
		public static function toInt($n){
			return pack("i".$n);
		}
	}

	class EtFile
	{
		private $filedata;
		private $filedatacomp;
		private $filesize;
		private $filesizecomp;
		private $location;
		private $offset;
		
		function __construct($filename, $location)
		{
			$this->filesize = filesize($filename);

			$handle = fopen($filename, "r");
			$this->filedata = fread($handle, $this->filesize);
			fclose($handle);

			$this->filedatacomp = zlib_encode($this->filedata, ZLIB_ENCODING_DEFLATE, 1);
			if($this->filedatacomp == false)
			{
				throw new Exception("Failed to compress() file [$filename]!");
			}
			$this->location = $location;
			$this->filesizecomp = strlen(bin2hex($this->filedatacomp))/2;
		}

		private function getFileData()
		{
			return $this->filedatacomp;
		}

		private function getBinary()
		{
			$data = $this->location;
			$data .= Utils::toNull(256 - strlen($this->location));
			$data .= Utils::toInt($this->filesizecomp);
			$data .= Utils::toInt($this->filesize);
			$data .= Utils::toInt($this->filesizecomp);
			$data .= Utils::toInt($this->offset); 
			$data .= Utils::toInt(0); 
			$data .= Utils::toInt(0); 
			$data .= Utils::toNull(36);
			return $data;
		}
	}

	class EtFileSystem
	{
		private $file; //File pointer
		private $current_file;
		
		const $header_magic = "EyedentityGames Packing File 0.1";
		const $header_version = 0xB;
		const $filecount = 123654;
		const $fileoffset = 8888;

		private $files = array(); //Files class

		function __construct($filename)
		{
			$this->file = fopen($filename, 'w');
			$this->current_file = $filename;
			$this->WriteHeader();
		}

		private function AddFile($filename, $location)
		{
			if(file_exists($filename) === false)
			{
				throw new Exception("[AddFile] $filename does not exist!");
			}
			array_push($this->files, new EtFile($filename, $location));
		}

		private function CloseFileSystem()
		{
			$this->WriteData();
			$this->WriteFooter();
			fclose($this->file);
		}

		private function WriteHeader()
		{
			fwrite($this->file, self::header_magic);
			fwrite($this->file, Utils::toNull(224));
			fwrite($this->file, Utils::toInt(self::header_version));
			fwrite($this->file, Utils::toInt(self::filecount));
			fwrite($this->file, Utils::toInt(self::fileoffset));
			fwrite($this->file, Utils::toInt(0)); //bRequireHeaderWrite
			fwrite($this->file, Utils::toNull(752));
		}

		private function RewriteHeader()
		{
			self::filecount = count($this->files);
			self::fileoffset = ftell($this->file);

			fseek($this->file, 256+4);
			fwrite($this->file, toInt(self::filecount));
			fwrite($this->file, toInt(self::fileoffset));
			fseek($this->file, self::fileoffset, SEEK_SET);
		}

		private function WriteData()
		{
			foreach($this->files as $f)
			{
				$f->offset = ftell($this->file);
				fwrite($this->file, $f->getFileData());
			}
		}

		private function WriteFooter()
		{
			$this->RewriteHeader();
			foreach($this->files as $f)
			{
				fwrite($this->file, $f->getBinary());
			}
		}
	}
}

$pak = new \RLKT\Pak\EtFileSystem("test.pak");
$pak->AddFile("D:\\testfile.xml", "\\testfile.xml");
$pak->CloseFileSystem();

?>
